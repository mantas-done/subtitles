<?php

// http://www.theneitherworld.com/mcpoodle/SCC_TOOLS/DOCS/

// 32 characters, 4 lines
// every frame transmits 2 bytes of data (two letters)
// : - non drop frame - counts frames (not time)
// ; - drop frame - counts time (not frames)
// non drop frame plays at 29.97 fps, so in one hour there is fewer frames. Non drop frame time in scc 1:00:00:00 = 1:00:03;18 in real time
// 3.6 seconds difference https://sonix.ai/resources/what-is-drop-frame-vs-non-drop-frame-timecode/
// scc time is earlier that srt time, because it needs to account for time it takes to send the text

// 94ae - Erase Non-displayed [buffer] Memory, with a code of 94ae
// 9420 - start pop-on caption
// 9470 - put the cursor on the bottom line start
// captions
// 942f - to display the caption in the buffer on the screen, the command EOC (End Of Caption), code 942f, is used.
// 942c - To clear the screen in preparation for drawing the caption, the command EDM (Erase Displayed Memory), code 942c, is used.

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;

class SccConverter implements ConverterContract
{
    private static $valid_fpses = [
        29.97,
        23.976,
    ];

    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return preg_match('/Scenarist_SCC V1.0/', $file_content) === 1;
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $fps = 29.97;
        if (!in_array($fps, self::$valid_fpses)) {
            throw new \Exception('invalid fps ' . $fps);
        }

        preg_match_all('/^(\d{2}:\d{2}:\d{2}[;:]\d{2})\s+(.*)$/m', $file_content, $matches, PREG_SET_ORDER);
        $parsed = [];
        foreach ($matches as $match) {
            $time = $match[1];
            $data = $match[2];

            $tmp_time = self::sccTimeToInternal($time, 0, $fps);
            $parsed[] = [
                'time' => self::sccTimeToInternal($time, self::codesToBytes($data), $fps),
                'lines' => self::sccToLines($data),
                'clear_display_at' => self::clearDisplayAt($tmp_time, $data, $fps),
            ];
        }

        $internal_format = [];
        $i = 0;
        foreach ($parsed as $j => $row) {
            // if there was clear display code in the text
            if ($i !== 0 && $row['clear_display_at'] !== null) {
                $internal_format[$i - 1]['end'] = $row['clear_display_at'];
            }
            if (!empty($row['lines'])) {
                if ($i !== 0 && !isset($internal_format[$i - 1]['end'])) {
                    $internal_format[$i - 1]['end'] = $row['time'];
                }
                $internal_format[$i] = [
                    'start' => $row['time'],
                    'lines' => $row['lines'],
                ];
                // If there are no further subtitles or EOC codes present, set the end time as the start time plus 1 sec.
                if (!isset($parsed[$j + 1])) {
                    $internal_format[$i]['end'] = $internal_format[$i]['start'] + 1;
                }
                $i++;
            } elseif (isset($internal_format[$i - 1])) {
                $internal_format[$i - 1]['end'] = $row['time'];
            }
        }

        return $internal_format;
    }

    /**
     * Convert library's "internal format" (array) to file's content
     *
     * @param array $internal_format    Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internal_format, array $output_settings): string
    {
        if (isset($output_settings['fps'])) {
            $fps = $output_settings['fps'];
        } else {
            $fps = 29.97;
        }
        if (!in_array($fps, self::$valid_fpses)) {
            throw new \Exception('invalid fps ' . $fps);
        }
        $ndf = false; // non drop frame
        if (isset($output_settings['ndf'])) {
            $ndf = $output_settings['ndf'];
        }

        $file_content = "Scenarist_SCC V1.0\r\n\r\n";

        $last_start_time = 0;
        $last_end_time = 0;
        foreach ($internal_format as $k => $block) {
            $time_available = $block['start'] - $last_start_time;
            $frames_available = floor($fps * $time_available);
            if ($k === 0) {
                $frames_available = 10000; // for the first text no limitations on sending time
            }
            if ($frames_available < 6) { // 2 blocks for start + 1 block for line + 1 block of text + 1 block for end + 1 block in case of clearing buffer
                // to little time to show something
                continue;
            }
            $lines = self::splitLongLines($block['lines'], $output_settings);
            $codes = self::textToSccText($lines);

            $code_blockes = explode(' ', $codes); // missing 2 start and 1 end block
            $frames_to_send_text = count($code_blockes);
            $frames_to_send = $frames_to_send_text + 2 + 1 + 1; // 2 for start, 1 for end, 1 in case need to clear buffer
            $to_many_frames = $frames_to_send - $frames_available;
            if ($to_many_frames > 0) {
                if (isset($output_settings['strict']) && $output_settings['strict']) {
                    throw new UserException('There is not enough time to send the text in SCC format. Shorten the text or increase the time gap between captions: ' . SrtConverter::internalTimeToSrt($block['start']) . ' -> ' . implode(' / ', $block['lines']));
                }
                $code_blockes = array_slice($code_blockes, 0, -($to_many_frames + 1)); // remove blocks that we won't be able to send
                $code_blockes[] = 'aeae'; // ..
            }
            array_unshift($code_blockes, '94ae', '9420');
            $code_blockes[] = '942f';
            $frames_to_send_text = count($code_blockes);
            $frames_to_send = $frames_to_send_text + 1; //  1 in case need to clear buffer

            // do we need to erase display buffer?
            $probable_start_sending_time = $block['start'] - ($frames_to_send / $fps);
            $separate_block_end = false;
            if ($k !== 0 && $last_end_time > $probable_start_sending_time) {
                $add_after_code_blocks = ($last_end_time - $probable_start_sending_time) * $fps;
                $add_after_code_blocks = (int)round($add_after_code_blocks);
                $add_after_code_blocks = max($add_after_code_blocks, 3); // min after 3 blocks
                $add_after_code_blocks = min($add_after_code_blocks, count($code_blockes) - 1); // do not place code block after the last item
                array_splice($code_blockes, $add_after_code_blocks, 0, '942c');
            } else {
                $separate_block_end = true;
            }

            $full_codes = implode(' ', $code_blockes);
            if ($separate_block_end && $k !== 0) {
                $file_content .= self::internalTimeToScc($last_end_time, 2, $fps, $ndf) . "\t" . '942c' . "\r\n\r\n";
            }

            $file_content .= self::internalTimeToScc($block['start'], count($code_blockes) * 2, $fps, $ndf) . "\t" . $full_codes . "\r\n\r\n";

            $last_start_time = $block['start'];
            $last_end_time = $block['end'];
        }
        if (isset($block)) { // stop last caption
            $file_content .= self::internalTimeToScc($block['end'], 2, $fps, $ndf) . "\t" . '942c' . "\r\n\r\n";
        }
        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .scc file format to internal time format (float in seconds)
     * Example: 00:02:17,44 -> 137.44
     *
     * @param $scc_time
     *
     * @return float
     */
    public static function sccTimeToInternal($scc_time, $text_bytes, $fps)
    {
        $tmp = str_replace(';', ':', $scc_time);
        $parts = explode(':', $tmp);
        $time = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2] + (int)$parts[3] / $fps;
        $time += ($text_bytes / 2) / $fps;

        if (strstr($scc_time, ';') !== false) {
            // drop frame
            return $time;
        } else {
            // non drop frame
            return $time / 3600 * 3603.6;
        }
    }

    /**
     * Convert internal time format (float in seconds) to .scc time format
     * Example: 137.44 -> 00:02:17,44
     *
     * @param float $internal_time
     *
     * @return string
     */
    public static function internalTimeToScc($internal_time, $text_bytes, $fps, $is_ndf)
    {
        $time = $internal_time;
        if ($is_ndf) {
            $time = $time * 3600 / 3603.6;
        }
        $time = $time - (($text_bytes / 2) - 1) / $fps; // -1 - because the last code is parsed before the frame is shown
        $time = max($time, 0); // min 0
        $parts = explode('.', $time);
        $whole = (int) $parts[0];
        $decimal = isset($parts[1]) ? (float)('0.' . $parts[1]) : 0.0;
        $frame = round($decimal * $fps);
        $frame = min($frame, floor($fps)); // max 29

        $separator = $is_ndf ? ':' : ';';
        $srt_time = gmdate("H:i:s", floor($whole)) . $separator . sprintf("%02d", $frame);

        return $srt_time;
    }

    // http://www.theneitherworld.com/mcpoodle/SCC_TOOLS/DOCS/SCC_TOOLS.HTML
    // 00:01:14:20 9425 9425 94ad 94ad 9470 9470 d94f d552 20d0 4cc1 4345 2054 4f20 4c45 c152 ce20 c1ce c420 54c1 4ccb
    // line positions, the last one 9470 9470
    // 94d0 above the last one
    // 1370 above the 94d0
    // 13d0 above the 1370
    protected static function textToSccText($lines)
    {
        $count = count($lines);
        $positions = [
            '13d0', // 4th from the bottom line
            '1370', // 3th from the bottom line
            '94d0', // 2th from the bottom line
            '9470', // bottom line
        ];
        $codes = '';
        foreach ($lines as $k => $line) {
            $codes .= ' ' . $positions[4 - $count + $k]; // aligns text to the bottom
            $codes .= ' ' . self::lineToCodes($line);
        }
        return trim($codes);
    }

    // makes max 4 lines with up to 32 characters each
    public static function splitLongLines($lines, $output_settings)
    {
        $new_lines = [];
        if (mb_strlen($lines[0]) > 32) {
            if (isset($output_settings['strict']) && $output_settings['strict']) {
                throw new UserException('SCC format supports lines up to 32 characters, this line is longer: "' . $lines[0] . '"');
            }
            $tmp_lines = explode("\n", Helpers::mb_wordwrap($lines[0], 32, "\n", true));
            if (isset($tmp_lines[2])) {
                $tmp_lines[1] = substr_replace($tmp_lines[1], '...', -3);
            }
            $new_lines[] = $tmp_lines[0];
            $new_lines[] = $tmp_lines[1];
        } else {
            $new_lines[] = $lines[0];
        }
        if (isset($lines[1])) {
            if (mb_strlen($lines[1]) > 32) {
                if (isset($output_settings['strict']) && $output_settings['strict']) {
                    throw new UserException('SCC format supports lines up to 32 characters, this line is longer: "' . $lines[1] . '"');
                }
                $tmp_lines = explode("\n", Helpers::mb_wordwrap($lines[1], 32, "\n", true));
                if (isset($tmp_lines[2])) {
                    $tmp_lines[1] = substr_replace($tmp_lines[1], '...', -3);
                }
                $new_lines[] = $tmp_lines[0];
                $new_lines[] = $tmp_lines[1];
            } else {
                $new_lines[] = $lines[1];
            }
        }

        return $new_lines;
    }

    public static function lineToCodes($line)
    {
        $reversed_characters = array_flip(self::$characters);
        $reversed_special = array_flip(self::$special_chars);
        $codes = '';
        $line = self::replaceNotSupportedCharactersByScc($line);
        $length = mb_strlen($line, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $character = mb_substr($line, $i, 1, 'UTF-8');
            if (isset($reversed_characters[$character])) {
                $codes .= $reversed_characters[$character];

            } elseif (isset($reversed_special[$character])) {
                if (strlen($codes) % 4 === 2) {
                    $codes .= '80'; // fill
                }
                $codes .= $reversed_special[$character];
            } elseif (($extended = self::getExtendedByCharacter($character)) !== null) {
                $codes .= $reversed_characters[$extended['prefix']];
                if (strlen($codes) % 4 === 2) {
                    $codes .= '80'; // fill
                }
                $codes .= $extended['code'];
            } else {
                $codes .= $reversed_characters['#']; // no symbol
            }
        }

        if (strlen($codes) % 4 === 2) {
            $codes .= '80'; // fill
        }

        $codes = self::addSpaceAfter4Characters($codes);

        return $codes;
    }

    public static function getExtendedByCharacter($character)
    {
         foreach (self::$extended_chars2 as $data) {
             if ($data['letter'] === $character) {
                return $data;
             }
         }
         return null;
    }

    public static function getExtendedByCode($code)
    {
        foreach (self::$extended_chars2 as $data) {
            if ($data['code'] === $code) {
                return $data;
            }
        }
        return null;
    }

    protected static function addSpaceAfter4Characters($string) {
        $result = '';
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            // Add a space after every fourth character
            if ($i > 0 && $i % 4 === 0) {
                $result .= ' ';
            }

            $result .= $string[$i];
        }

        return $result;
    }

    public static function sccToLines($data)
    {
        $single_space = preg_replace('/\s+/', ' ', $data);
        $blocks = explode(' ', trim($single_space));
        $blocks = array_map('strtolower', $blocks);
        $text = '';
        foreach ($blocks as $block) {
            // command
            if (isset(self::$commands[$block])) {
                if (strpos(self::$commands[$block], 'break') !== false) {
                    $text .= "\n";
                }
                continue;
            }

            if (($extended = self::getExtendedByCode($block)) !== null) {
                $text = mb_substr($text, 0, -1); // remove one character, because extended characters are composed from 2 characters
                $text .= $extended['letter'];
                continue;
            }

            if (isset(self::$special_chars[$block])) {
                $text .= self::$special_chars[$block];
                continue;
            }

            // text
            $part1 = substr($block, 0, 2);
            $part2 = substr($block, 2, 2);
            if (!isset(self::$characters[$part1]) || !isset(self::$characters[$part2])) {
                continue; // ignore unknown block
                // throw new \Exception('unknown block: ' . $block);
            }
            $text .= self::$characters[$part1] . self::$characters[$part2];
        }

        $lines = explode("\n", $text);
        $lines = self::removeEmptyLinesFromArray($lines);

        return $lines;
    }

    private static function removeEmptyLinesFromArray($array)
    {
        $result = array_filter($array, function($value) {
            return trim($value) !== '';
        });

        return array_values($result);
    }

    private static function codesToBytes(string $codes): int // AD00 FFAA
    {
        return $bytes = strlen(str_replace(' ', '', $codes)) / 2;
    }

    private static function clearDisplayAt($time, $data, $fps)
    {
        $new_data = preg_replace('/\s+/', ' ', $data);
        $codes = explode(' ', $new_data);
        foreach ($codes as $k => $code) {
            if ($code === '942c') {
                return $time + $k / $fps;
            }
        }
        return null;

    }

    private static function replaceNotSupportedCharactersByScc($string)
    {
        $replacements = [
            // https://www.uspto.gov/custom-page/characters-conversion-table
            '‚' => ',', //	low left rising single quote
            'ƒ' => 'f', //	small italic f, function of,florin
            '„' => ',,', //	low left rising double quote
            '…' => '.', //	low horizontal ellipsis
            'ˆ' => '^', //	modifier letter circumflex accent
            '‰' => '0/00', //	per thousand (mille) sign
            'Š' => 'S', //	capital S caron or hacek
            '‹' => '<', //	left single angle quote mark
            'Œ' => 'OE', //	capital OE ligature
            'Ž' => 'Z', //	latin capital letter Z with caron
            '˜' => '~', //	small spacing tilde accent
            'š' => 's', //	small s caron or hacek
            '›' => '>', //	right single angle quote mark
            'œ' => 'oe', //	small oe ligature
            'ž' => 'z', //	latin small letter Z with caron
            'Ÿ' => 'Y', //	capital Y dieresis or umlaut
            '¬' => '–', //	not sign
            'Ā' => 'A', //	Amacr - latin capital letter A with macron
            'ā' => 'a', //	amacr - latin small letter a with macron
            'Ă' => 'A', //	Acaron - latin capital letter A with caron (breve)
            'ă' => 'a', //	acaron - latin small letter a with caron (breve)
            'Ą' => 'A', //	Acedil - latin capital letter A with cedilla
            'ą' => 'a', //	acedil - latin small letter a with cedilla
            'Ć' => 'C', //	Cacute - latin capital letter C with acute
            'ć' => 'c', //	cacute - latin small letter c with acute
            'Č' => 'C', //	Ccaron - latin capital letter C with caron
            'č' => 'c', //	ccaron - latin small letter c with caron
            'Ď' => 'D', //	dcaron - latin capital letter D with caron
            'ď' => 'd', //	latin small letter d with caron
            'Đ' => 'D', //	dstrok - latin capital letter D with stroke
            'đ' => 'd', //	dstrok - latin small letter d with stroke
            'Ē' => 'E', //	emacr - latin capital letter E with macron
            'ē' => 'e', //	latin small letter e with macron
            'Ĕ' => 'E', //	latin capital letter E with breve
            'ĕ' => 'e', //	latin small letter e with macron
            'Ė' => 'E', //	edot - latin capital letter E with dot above
            'ė' => 'e', //	edot - latin small letter e with dot above
            'Ę' => 'E', //	ecedil - latin capital letter E with cedilla
            'ę' => 'e', //	ecedil - latin small letter e with cedilla
            'Ě' => 'E', //	ecaron - latin capital letter E with caron
            'ě' => 'e', //	ecaron - latin small letter e with caron
            'Ğ' => 'G', //	gcaron - latin capital letter G with caron (breve)
            'ğ' => 'g', //	gcaron - latin small letter g with caron (breve)
            'Ģ' => 'G', //	gcedil - latin capital letter g with cedilla
            'ģ' => 'g', //	gapos - latin small letter g with cedilla above
            'Ĥ' => 'H', //	xxxx - latin capital letter h with circumflex above
            'ĥ' => 'h', //	latin small letter h with circumflex above
            'Ħ' => 'H', //	latin capital letter h with stroke
            'ħ' => 'h', //	latin small letter h with circumflex above
            'Ĩ' => 'I', //	latin capital letter i with tilde
            'ĩ' => 'i', //	latin small letter i with tilde
            'Ī' => 'I', //	imacr - latin capital letter I with macron
            'ī' => 'i', //	imacr - latin small letter i with macron
            'Į' => 'I', //	iogon - latin capital letter i with ogonek
            'į' => 'i', //	iogon - latin small letter i with ogonek
            'İ' => 'I', //	icedil - latin capital letter i with cedilla
            'ı' => 'i', //	nodot latin small letter i with no dot
            'Ĳ' => 'IJ', //	latin capital ligature ij
            'ĳ' => 'ij', //	latin small ligature ij
            'Ĵ' => 'K', //	latin capital letter k with circumflex
            'ĵ' => 'k', //	latin small letter k with circumflex
            'Ķ' => 'K', //	kcedil - latin capital letter k with cedilla
            'ķ' => 'k', //	kcedil - latin small letter k with cedilla
            'ĸ' => 'K', //	latin small letter kra
            'Ĺ' => 'L', //	lacute - latin capital letter l with acute
            'ĺ' => 'l', //	lacute - latin small letter l with acute
            'Ļ' => 'L', //	lcedil - latin capital letter l with cedilla
            'ļ' => 'l', //	lcedil - latin small letter l with cedilla
            'Ľ' => 'L', //	lcaron - latin capital letter l with
            'ľ' => 'l', //	lcaron - latin small letter l with caron
            'Ŀ' => 'L', //	latin capital letter l with middle dot
            'ŀ' => 'l', //	latin small letter l with middle dot
            'Ł' => 'L', //	lstrok - latin capital letter l with stroke
            'ł' => 'l', //	lstrok - latin small letter l with stroke
            'Ń' => 'N', //	nacute - latin capital letter n with acute
            'ń' => 'n', //	nacute - latin small letter n with acute
            'Ņ' => 'N', //	ncedil - latin capital letter N with cedilla
            'ņ' => 'n', //	ncedil - latin small letter n with cedilla
            'Ň' => 'N', //	ncaron - latin capital letter n with caron
            'ň' => 'n', //	ncaron - latin small letter n with caron
            'ŉ' => 'n', //	latin small letter n preceded by apostophe
            'Ŋ' => 'N', //	latin capital letter eng
            'ŋ' => 'n', //	latin small letter eng
            'Ō' => 'O', //	omacr - latin capital letter o with macron
            'ō' => 'o', //	omacr - latin small letter o with macron
            'Ŏ' => 'O', //	latin capital letter o with breve
            'ŏ' => 'o', //	latin small letter o with breve
            'Ő' => 'O', //	odblac - latin capital letter O with double acute
            'ő' => 'o', //	odblac - latin small letter o with double acute
            'Ŕ' => 'R', //	racute - latin capital letter r with acute
            'ŕ' => 'r', //	racute - latin small letter r with acute
            'Ŗ' => 'R', //	rcedil - latin capital letter r with cedilla
            'ŗ' => 'r', //	rcedil - latin small letter r with cedilla
            'Ř' => 'R', //	rcaron - latin capital letter r with caron
            'ř' => 'r', //	rcaron - latin small letter r with caron
            'Ś' => 'S', //	sacute - latin capital letter s with acute
            'ś' => 's', //	sacute - latin small letter s with acute
            'Ŝ' => 'S', //	latin capital letter s with circumflex
            'ŝ' => 's', //	latin small letter s with circumflex
            'Ş' => 'S', //	scedil - latin capital letter s with cedilla
            'ş' => 's', //	scedil - latin small letter s with cedilla
            'Ţ' => 'T', //	tcedil - latin capital letter t with cedilla
            'ţ' => 't', //	tcedil - latin small letter t with cedilla
            'Ť' => 'T', //	tcaron - latin capital letter t with caron
            'ť' => 't', //	tcaron - latin small letter t with caron
            'Ŧ' => 'T', //	latin capital letter t with stroke
            'ŧ' => 't', //	latin small letter t with stroke
            'Ũ' => 'U', //	latin capital letter u with tilde
            'ũ' => 'u', //	latin small letter u with tilde
            'Ū' => 'U', //	umacr - latin capital letter u with macron
            'ū' => 'u', //	umacr - latin small letter u with macron
            'Ŭ' => 'U', //	latin capital letter u with breve
            'ŭ' => 'u', //	latin small letter u with breve
            'Ů' => 'U', //	uring - latin capital letter u with ring above
            'ů' => 'u', //	uring - latin small letter u with ring above
            'Ű' => 'U', //	udblac - latin capital letter u with double acute
            'ű' => 'u', //	udblac - latin small letter u with double acute
            'Ŵ' => 'W', //	latin capital letter w with circumflex
            'ŵ' => 'w', //	latin small letter w with circumflex
            'Ŷ' => 'Y', //	latin capital letter y with circumflex
            'ŷ' => 'y', //	latin smalll letter y with circumflex
            'Ź' => 'Z', //	zacute - latin capital letter z with acute
            'ź' => 'z', //	zacute - latin small letter z with acute
            'Ż' => 'Z', //	zdot - latin capital letter z with dot above
            'ż' => 'z', //	zdot - latin small letter z with dot above
            'ʻ' => '\'', //	modifier letter turned comma
            'ʼ' => '\'', //	modifier letter apostrophe
            '·' => '.', //	greek and teleia
            'Φ' => 'Ph', //	greek capital letter phi
            'Ψ' => 'Ps', //	greek capital letter psi
            'Ω' => 'O', //	greek capital letter omega
            'β' => 'B', //	greek small letter beta
            'Ᾰ' => 'A', //	punctuation
            'Ᾱ' => 'A', //	thin
            'Ὰ' => 'A', //	hair
            '‐' => '-', //	hyphen
            '‑' => '-', //	non-breaking hyphen
            '‒' => '-', //	figure dash
            '–' => '-', //	en dash
            '‛' => '\'', //	single high reversed quotation mark
            '‟' => '"', //	double high reversed quotation mark
            '․' => '.', //	one dot leader
            '⁚' => ':', //	two dot punctuation
            '∙' => '.', //	bullet operator
            'Ⓡ' => '(R)', //	circled latin capital letter R
            '、' => '\'', //	ideographic comma
            '〃' => '"', //	ditto mark

            // more custom letters
            'æ' => 'ae',
            'Æ' => 'AE',
            '€' => 'EUR',
            '₹' => 'INR',
            '₽' => 'RUB',
            '₱' => 'PHP',
            '₿' => 'BTC'
        ];
        $string = strtr($string, $replacements);

        return $string;
    }

    // from https://github.com/pbs/pycaption/blob/main/pycaption/scc/constants.py
    private static $commands = [
        '9420' => '',
        '9429' => '',
        '9425' => '',
        '9426' => '',
        '94a7' => '',
        '942a' => '',
        '94ab' => '',
        '942c' => '',
        '94ae' => '',
        '942f' => '',
        '1c20' => '',
        '1c29' => '',
        '1c25' => '',
        '1c26' => '',
        '1ca7' => '',
        '1c2a' => '',
        '1cab' => '',
        '1c2c' => '',
        '1cae' => '',
        '1c2f' => '',
        '1520' => '',
        '1529' => '',
        '1525' => '',
        '1526' => '',
        '15a7' => '',
        '152a' => '',
        '15ab' => '',
        '152c' => '',
        '15ae' => '',
        '152f' => '',
        '9d20' => '',
        '9d29' => '',
        '9d25' => '',
        '9d26' => '',
        '9da7' => '',
        '9d2a' => '',
        '9dab' => '',
        '9d2c' => '',
        '9dae' => '',
        '9d2f' => '',
        '9779' => '<$>{break}<$>',
        '9775' => '<$>{break}<$>',
        '9776' => '<$>{break}<$>',
        '9770' => '<$>{break}<$>',
        '9773' => '<$>{break}<$>',
        '10c8' => '<$>{break}<$>',
        '10c2' => '<$>{break}<$>',
        '166e' => '<$>{break}<$>{italic}<$>',
        '166d' => '<$>{break}<$>',
        '166b' => '<$>{break}<$>',
        '10c4' => '<$>{break}<$>',
        '9473' => '<$>{break}<$>',
        '977f' => '<$>{break}<$>',
        '977a' => '<$>{break}<$>',
        '1668' => '<$>{break}<$>',
        '1667' => '<$>{break}<$>',
        '1664' => '<$>{break}<$>',
        '1661' => '<$>{break}<$>',
        '10ce' => '<$>{break}<$>{italic}<$>',
        '94c8' => '<$>{break}<$>',
        '94c7' => '<$>{break}<$>',
        '94c4' => '<$>{break}<$>',
        '94c2' => '<$>{break}<$>',
        '94c1' => '<$>{break}<$>',
        '915e' => '<$>{break}<$>',
        '915d' => '<$>{break}<$>',
        '915b' => '<$>{break}<$>',
        '925d' => '<$>{break}<$>',
        '925e' => '<$>{break}<$>',
        '925b' => '<$>{break}<$>',
        '97e6' => '<$>{break}<$>',
        '97e5' => '<$>{break}<$>',
        '97e3' => '<$>{break}<$>',
        '97e0' => '<$>{break}<$>',
        '97e9' => '<$>{break}<$>',
        '9154' => '<$>{break}<$>',
        '9157' => '<$>{break}<$>',
        '9151' => '<$>{break}<$>',
        '9258' => '<$>{break}<$>',
        '9152' => '<$>{break}<$>',
        '9257' => '<$>{break}<$>',
        '9254' => '<$>{break}<$>',
        '9252' => '<$>{break}<$>',
        '9158' => '<$>{break}<$>',
        '9251' => '<$>{break}<$>',
        '94cd' => '<$>{break}<$>',
        '94ce' => '<$>{break}<$>{italic}<$>',
        '94cb' => '<$>{break}<$>',
        '97ef' => '<$>{break}<$>{italic}<$>',
        '1373' => '<$>{break}<$>',
        '97ec' => '<$>{break}<$>',
        '97ea' => '<$>{break}<$>',
        '15c7' => '<$>{break}<$>',
        '974f' => '<$>{break}<$>{italic}<$>',
        '10c1' => '<$>{break}<$>',
        '974a' => '<$>{break}<$>',
        '974c' => '<$>{break}<$>',
        '10c7' => '<$>{break}<$>',
        '976d' => '<$>{break}<$>',
        '15d6' => '<$>{break}<$>',
        '15d5' => '<$>{break}<$>',
        '15d3' => '<$>{break}<$>',
        '15d0' => '<$>{break}<$>',
        '15d9' => '<$>{break}<$>',
        '9745' => '<$>{break}<$>',
        '9746' => '<$>{break}<$>',
        '9740' => '<$>{break}<$>',
        '9743' => '<$>{break}<$>',
        '9749' => '<$>{break}<$>',
        '15df' => '<$>{break}<$>',
        '15dc' => '<$>{break}<$>',
        '15da' => '<$>{break}<$>',
        '15f8' => '<$>{break}<$>',
        '94fe' => '<$>{break}<$>',
        '94fd' => '<$>{break}<$>',
        '94fc' => '<$>{break}<$>',
        '94fb' => '<$>{break}<$>',
        '944f' => '<$>{break}<$>{italic}<$>',
        '944c' => '<$>{break}<$>',
        '944a' => '<$>{break}<$>',
        '92fc' => '<$>{break}<$>',
        '1051' => '<$>{break}<$>',
        '1052' => '<$>{break}<$>',
        '1054' => '<$>{break}<$>',
        '92fe' => '<$>{break}<$>',
        '92fd' => '<$>{break}<$>',
        '1058' => '<$>{break}<$>',
        '157a' => '<$>{break}<$>',
        '157f' => '<$>{break}<$>',
        '9279' => '<$>{break}<$>',
        '94f4' => '<$>{break}<$>',
        '94f7' => '<$>{break}<$>',
        '94f1' => '<$>{break}<$>',
        '9449' => '<$>{break}<$>',
        '92fb' => '<$>{break}<$>',
        '9446' => '<$>{break}<$>',
        '9445' => '<$>{break}<$>',
        '9443' => '<$>{break}<$>',
        '94f8' => '<$>{break}<$>',
        '9440' => '<$>{break}<$>',
        '1057' => '<$>{break}<$>',
        '9245' => '<$>{break}<$>',
        '92f2' => '<$>{break}<$>',
        '1579' => '<$>{break}<$>',
        '92f7' => '<$>{break}<$>',
        '105e' => '<$>{break}<$>',
        '92f4' => '<$>{break}<$>',
        '1573' => '<$>{break}<$>',
        '1570' => '<$>{break}<$>',
        '1576' => '<$>{break}<$>',
        '1575' => '<$>{break}<$>',
        '16c1' => '<$>{break}<$>',
        '16c2' => '<$>{break}<$>',
        '9168' => '<$>{break}<$>',
        '16c7' => '<$>{break}<$>',
        '9164' => '<$>{break}<$>',
        '9167' => '<$>{break}<$>',
        '9161' => '<$>{break}<$>',
        '9162' => '<$>{break}<$>',
        '947f' => '<$>{break}<$>',
        '91c2' => '<$>{break}<$>',
        '91c1' => '<$>{break}<$>',
        '91c7' => '<$>{break}<$>',
        '91c4' => '<$>{break}<$>',
        '13e3' => '<$>{break}<$>',
        '91c8' => '<$>{break}<$>',
        '91d0' => '<$>{break}<$>',
        '13e5' => '<$>{break}<$>',
        '13c8' => '<$>{break}<$>',
        '16cb' => '<$>{break}<$>',
        '16cd' => '<$>{break}<$>',
        '16ce' => '<$>{break}<$>{italic}<$>',
        '916d' => '<$>{break}<$>',
        '916e' => '<$>{break}<$>{italic}<$>',
        '916b' => '<$>{break}<$>',
        '91d5' => '<$>{break}<$>',
        '137a' => '<$>{break}<$>',
        '91cb' => '<$>{break}<$>',
        '91ce' => '<$>{break}<$>{italic}<$>',
        '91cd' => '<$>{break}<$>',
        '13ec' => '<$>{break}<$>',
        '13c1' => '<$>{break}<$>',
        '13ea' => '<$>{break}<$>',
        '13ef' => '<$>{break}<$>{italic}<$>',
        '94f2' => '<$>{break}<$>',
        '97fb' => '<$>{break}<$>',
        '97fc' => '<$>{break}<$>',
        '1658' => '<$>{break}<$>',
        '97fd' => '<$>{break}<$>',
        '97fe' => '<$>{break}<$>',
        '1652' => '<$>{break}<$>',
        '1651' => '<$>{break}<$>',
        '1657' => '<$>{break}<$>',
        '1654' => '<$>{break}<$>',
        '10cb' => '<$>{break}<$>',
        '97f2' => '<$>{break}<$>',
        '97f1' => '<$>{break}<$>',
        '97f7' => '<$>{break}<$>',
        '97f4' => '<$>{break}<$>',
        '165b' => '<$>{break}<$>',
        '97f8' => '<$>{break}<$>',
        '165d' => '<$>{break}<$>',
        '165e' => '<$>{break}<$>',
        '15cd' => '<$>{break}<$>',
        '10cd' => '<$>{break}<$>',
        '9767' => '<$>{break}<$>',
        '9249' => '<$>{break}<$>',
        '1349' => '<$>{break}<$>',
        '91d9' => '<$>{break}<$>',
        '1340' => '<$>{break}<$>',
        '91d3' => '<$>{break}<$>',
        '9243' => '<$>{break}<$>',
        '1343' => '<$>{break}<$>',
        '91d6' => '<$>{break}<$>',
        '1345' => '<$>{break}<$>',
        '1346' => '<$>{break}<$>',
        '9246' => '<$>{break}<$>',
        '94e9' => '<$>{break}<$>',
        '94e5' => '<$>{break}<$>',
        '94e6' => '<$>{break}<$>',
        '94e0' => '<$>{break}<$>',
        '94e3' => '<$>{break}<$>',
        '15ea' => '<$>{break}<$>',
        '15ec' => '<$>{break}<$>',
        '15ef' => '<$>{break}<$>{italic}<$>',
        '16fe' => '<$>{break}<$>',
        '16fd' => '<$>{break}<$>',
        '16fc' => '<$>{break}<$>',
        '16fb' => '<$>{break}<$>',
        '1367' => '<$>{break}<$>',
        '94ef' => '<$>{break}<$>{italic}<$>',
        '94ea' => '<$>{break}<$>',
        '94ec' => '<$>{break}<$>',
        '924a' => '<$>{break}<$>',
        '91dc' => '<$>{break}<$>',
        '924c' => '<$>{break}<$>',
        '91da' => '<$>{break}<$>',
        '91df' => '<$>{break}<$>',
        '134f' => '<$>{break}<$>{italic}<$>',
        '924f' => '<$>{break}<$>{italic}<$>',
        '16f8' => '<$>{break}<$>',
        '16f7' => '<$>{break}<$>',
        '16f4' => '<$>{break}<$>',
        '16f2' => '<$>{break}<$>',
        '16f1' => '<$>{break}<$>',
        '15e0' => '<$>{break}<$>',
        '15e3' => '<$>{break}<$>',
        '15e5' => '<$>{break}<$>',
        '15e6' => '<$>{break}<$>',
        '15e9' => '<$>{break}<$>',
        '9757' => '<$>{break}<$>',
        '9754' => '<$>{break}<$>',
        '9752' => '<$>{break}<$>',
        '9751' => '<$>{break}<$>',
        '9758' => '<$>{break}<$>',
        '92f1' => '<$>{break}<$>',
        '104c' => '<$>{break}<$>',
        '104a' => '<$>{break}<$>',
        '104f' => '<$>{break}<$>{italic}<$>',
        '105d' => '<$>{break}<$>',
        '92f8' => '<$>{break}<$>',
        '975e' => '<$>{break}<$>',
        '975d' => '<$>{break}<$>',
        '975b' => '<$>{break}<$>',
        '1043' => '<$>{break}<$>',
        '1040' => '<$>{break}<$>',
        '1046' => '<$>{break}<$>',
        '1045' => '<$>{break}<$>',
        '1049' => '<$>{break}<$>',
        '9479' => '<$>{break}<$>',
        '917f' => '<$>{break}<$>',
        '9470' => '<$>{break}<$>',
        '9476' => '<$>{break}<$>',
        '917a' => '<$>{break}<$>',
        '9475' => '<$>{break}<$>',
        '927a' => '<$>{break}<$>',
        '927f' => '<$>{break}<$>',
        '134a' => '<$>{break}<$>',
        '15fb' => '<$>{break}<$>',
        '15fc' => '<$>{break}<$>',
        '15fd' => '<$>{break}<$>',
        '15fe' => '<$>{break}<$>',
        '1546' => '<$>{break}<$>',
        '1545' => '<$>{break}<$>',
        '1543' => '<$>{break}<$>',
        '1540' => '<$>{break}<$>',
        '1549' => '<$>{break}<$>',
        '13fd' => '<$>{break}<$>',
        '13fe' => '<$>{break}<$>',
        '13fb' => '<$>{break}<$>',
        '13fc' => '<$>{break}<$>',
        '92e9' => '<$>{break}<$>',
        '92e6' => '<$>{break}<$>',
        '9458' => '<$>{break}<$>',
        '92e5' => '<$>{break}<$>',
        '92e3' => '<$>{break}<$>',
        '92e0' => '<$>{break}<$>',
        '9270' => '<$>{break}<$>',
        '9273' => '<$>{break}<$>',
        '9275' => '<$>{break}<$>',
        '9276' => '<$>{break}<$>',
        '15f1' => '<$>{break}<$>',
        '15f2' => '<$>{break}<$>',
        '15f4' => '<$>{break}<$>',
        '15f7' => '<$>{break}<$>',
        '9179' => '<$>{break}<$>',
        '9176' => '<$>{break}<$>',
        '9175' => '<$>{break}<$>',
        '947a' => '<$>{break}<$>',
        '9173' => '<$>{break}<$>',
        '9170' => '<$>{break}<$>',
        '13f7' => '<$>{break}<$>',
        '13f4' => '<$>{break}<$>',
        '13f2' => '<$>{break}<$>',
        '13f1' => '<$>{break}<$>',
        '92ef' => '<$>{break}<$>{italic}<$>',
        '92ec' => '<$>{break}<$>',
        '13f8' => '<$>{break}<$>',
        '92ea' => '<$>{break}<$>',
        '154f' => '<$>{break}<$>{italic}<$>',
        '154c' => '<$>{break}<$>',
        '154a' => '<$>{break}<$>',
        '16c4' => '<$>{break}<$>',
        '16c8' => '<$>{break}<$>',
        '97c8' => '<$>{break}<$>',
        '164f' => '<$>{break}<$>{italic}<$>',
        '164a' => '<$>{break}<$>',
        '164c' => '<$>{break}<$>',
        '1645' => '<$>{break}<$>',
        '1646' => '<$>{break}<$>',
        '1640' => '<$>{break}<$>',
        '1643' => '<$>{break}<$>',
        '1649' => '<$>{break}<$>',
        '94df' => '<$>{break}<$>',
        '94dc' => '<$>{break}<$>',
        '94da' => '<$>{break}<$>',
        '135b' => '<$>{break}<$>',
        '135e' => '<$>{break}<$>',
        '135d' => '<$>{break}<$>',
        '1370' => '<$>{break}<$>',
        '9240' => '<$>{break}<$>',
        '13e9' => '<$>{break}<$>',
        '1375' => '<$>{break}<$>',
        '1679' => '<$>{break}<$>',
        '1358' => '<$>{break}<$>',
        '1352' => '<$>{break}<$>',
        '1351' => '<$>{break}<$>',
        '1376' => '<$>{break}<$>',
        '1357' => '<$>{break}<$>',
        '1354' => '<$>{break}<$>',
        '1379' => '<$>{break}<$>',
        '94d9' => '<$>{break}<$>',
        '94d6' => '<$>{break}<$>',
        '94d5' => '<$>{break}<$>',
        '1562' => '<$>{break}<$>',
        '94d3' => '<$>{break}<$>',
        '94d0' => '<$>{break}<$>',
        '13e0' => '<$>{break}<$>',
        '13e6' => '<$>{break}<$>',
        '976b' => '<$>{break}<$>',
        '15c4' => '<$>{break}<$>',
        '15c2' => '<$>{break}<$>',
        '15c1' => '<$>{break}<$>',
        '976e' => '<$>{break}<$>{italic}<$>',
        '134c' => '<$>{break}<$>',
        '15c8' => '<$>{break}<$>',
        '92c8' => '<$>{break}<$>',
        '16e9' => '<$>{break}<$>',
        '16e3' => '<$>{break}<$>',
        '16e0' => '<$>{break}<$>',
        '16e6' => '<$>{break}<$>',
        '16e5' => '<$>{break}<$>',
        '91e5' => '<$>{break}<$>',
        '91e6' => '<$>{break}<$>',
        '91e0' => '<$>{break}<$>',
        '91e3' => '<$>{break}<$>',
        '13c4' => '<$>{break}<$>',
        '13c7' => '<$>{break}<$>',
        '91e9' => '<$>{break}<$>',
        '13c2' => '<$>{break}<$>',
        '9762' => '<$>{break}<$>',
        '15ce' => '<$>{break}<$>{italic}<$>',
        '9761' => '<$>{break}<$>',
        '15cb' => '<$>{break}<$>',
        '9764' => '<$>{break}<$>',
        '9768' => '<$>{break}<$>',
        '91ef' => '<$>{break}<$>{italic}<$>',
        '91ea' => '<$>{break}<$>',
        '91ec' => '<$>{break}<$>',
        '13ce' => '<$>{break}<$>{italic}<$>',
        '13cd' => '<$>{break}<$>',
        '97da' => '<$>{break}<$>',
        '13cb' => '<$>{break}<$>',
        '1362' => '<$>{break}<$>',
        '16ec' => '<$>{break}<$>',
        '16ea' => '<$>{break}<$>',
        '16ef' => '<$>{break}<$>{italic}<$>',
        '97c1' => '<$>{break}<$>',
        '97c2' => '<$>{break}<$>',
        '97c4' => '<$>{break}<$>',
        '97c7' => '<$>{break}<$>',
        '92cd' => '<$>{break}<$>',
        '92ce' => '<$>{break}<$>{italic}<$>',
        '92cb' => '<$>{break}<$>',
        '92da' => '<$>{break}<$>',
        '92dc' => '<$>{break}<$>',
        '92df' => '<$>{break}<$>',
        '97df' => '<$>{break}<$>',
        '155b' => '<$>{break}<$>',
        '155e' => '<$>{break}<$>',
        '155d' => '<$>{break}<$>',
        '97dc' => '<$>{break}<$>',
        '1675' => '<$>{break}<$>',
        '1676' => '<$>{break}<$>',
        '1670' => '<$>{break}<$>',
        '1673' => '<$>{break}<$>',
        '1662' => '<$>{break}<$>',
        '97cb' => '<$>{break}<$>',
        '97ce' => '<$>{break}<$>{italic}<$>',
        '97cd' => '<$>{break}<$>',
        '92c4' => '<$>{break}<$>',
        '92c7' => '<$>{break}<$>',
        '92c1' => '<$>{break}<$>',
        '92c2' => '<$>{break}<$>',
        '1551' => '<$>{break}<$>',
        '97d5' => '<$>{break}<$>',
        '97d6' => '<$>{break}<$>',
        '1552' => '<$>{break}<$>',
        '97d0' => '<$>{break}<$>',
        '1554' => '<$>{break}<$>',
        '1557' => '<$>{break}<$>',
        '97d3' => '<$>{break}<$>',
        '1558' => '<$>{break}<$>',
        '167f' => '<$>{break}<$>',
        '137f' => '<$>{break}<$>',
        '167a' => '<$>{break}<$>',
        '92d9' => '<$>{break}<$>',
        '92d0' => '<$>{break}<$>',
        '92d3' => '<$>{break}<$>',
        '92d5' => '<$>{break}<$>',
        '92d6' => '<$>{break}<$>',
        '10dc' => '<$>{break}<$>',
        '9262' => '<$>{break}<$>',
        '9261' => '<$>{break}<$>',
        '91f8' => '<$>{break}<$>',
        '10df' => '<$>{break}<$>',
        '9264' => '<$>{break}<$>',
        '91f4' => '<$>{break}<$>',
        '91f7' => '<$>{break}<$>',
        '91f1' => '<$>{break}<$>',
        '91f2' => '<$>{break}<$>',
        '97d9' => '<$>{break}<$>',
        '9149' => '<$>{break}<$>',
        '9143' => '<$>{break}<$>',
        '9140' => '<$>{break}<$>',
        '9146' => '<$>{break}<$>',
        '9145' => '<$>{break}<$>',
        '9464' => '<$>{break}<$>',
        '9467' => '<$>{break}<$>',
        '9461' => '<$>{break}<$>',
        '9462' => '<$>{break}<$>',
        '9468' => '<$>{break}<$>',
        '914c' => '<$>{break}<$>',
        '914a' => '<$>{break}<$>',
        '914f' => '<$>{break}<$>{italic}<$>',
        '10d3' => '<$>{break}<$>',
        '926b' => '<$>{break}<$>',
        '10d0' => '<$>{break}<$>',
        '10d6' => '<$>{break}<$>',
        '926e' => '<$>{break}<$>{italic}<$>',
        '926d' => '<$>{break}<$>',
        '91fd' => '<$>{break}<$>',
        '91fe' => '<$>{break}<$>',
        '10d9' => '<$>{break}<$>',
        '91fb' => '<$>{break}<$>',
        '91fc' => '<$>{break}<$>',
        '946e' => '<$>{break}<$>{italic}<$>',
        '946d' => '<$>{break}<$>',
        '946b' => '<$>{break}<$>',
        '10da' => '<$>{break}<$>',
        '10d5' => '<$>{break}<$>',
        '9267' => '<$>{break}<$>',
        '9268' => '<$>{break}<$>',
        '16df' => '<$>{break}<$>',
        '16da' => '<$>{break}<$>',
        '16dc' => '<$>{break}<$>',
        '9454' => '<$>{break}<$>',
        '9457' => '<$>{break}<$>',
        '9451' => '<$>{break}<$>',
        '9452' => '<$>{break}<$>',
        '136d' => '<$>{break}<$>',
        '136e' => '<$>{break}<$>{italic}<$>',
        '136b' => '<$>{break}<$>',
        '13d9' => '<$>{break}<$>',
        '13da' => '<$>{break}<$>',
        '13dc' => '<$>{break}<$>',
        '13df' => '<$>{break}<$>',
        '1568' => '<$>{break}<$>',
        '1561' => '<$>{break}<$>',
        '1564' => '<$>{break}<$>',
        '1567' => '<$>{break}<$>',
        '16d5' => '<$>{break}<$>',
        '16d6' => '<$>{break}<$>',
        '16d0' => '<$>{break}<$>',
        '16d3' => '<$>{break}<$>',
        '945d' => '<$>{break}<$>',
        '945e' => '<$>{break}<$>',
        '16d9' => '<$>{break}<$>',
        '945b' => '<$>{break}<$>',
        '156b' => '<$>{break}<$>',
        '156d' => '<$>{break}<$>',
        '156e' => '<$>{break}<$>{italic}<$>',
        '105b' => '<$>{break}<$>',
        '1364' => '<$>{break}<$>',
        '1368' => '<$>{break}<$>',
        '1361' => '<$>{break}<$>',
        '13d0' => '<$>{break}<$>',
        '13d3' => '<$>{break}<$>',
        '13d5' => '<$>{break}<$>',
        '13d6' => '<$>{break}<$>',
        '97a1' => '',
        '97a2' => '',
        '9723' => '',
        '94a1' => '',
        '94a4' => '',
        '94ad' => '',
        '1fa1' => '',
        '1fa2' => '',
        '1f23' => '',
        '1ca1' => '',
        '1ca4' => '',
        '1cad' => '',
        '15a1' => '',
        '15a4' => '',
        '15ad' => '',
        '9da1' => '',
        '9da4' => '',
        '9dad' => '',
        '1020' => '',
        '10a1' => '',
        '10a2' => '',
        '1023' => '',
        '10a4' => '',
        '1025' => '',
        '1026' => '',
        '10a7' => '',
        '10a8' => '',
        '1029' => '',
        '102a' => '',
        '10ab' => '',
        '102c' => '',
        '10ad' => '',
        '10ae' => '',
        '102f' => '',
        '97ad' => '',
        '97a4' => '',
        '9725' => '',
        '9726' => '',
        '97a7' => '',
        '97a8' => '',
        '9729' => '',
        '972a' => '',
        '9120' => '<$>{end-italic}<$>',
        '91a1' => '',
        '91a2' => '',
        '9123' => '',
        '91a4' => '',
        '9125' => '',
        '9126' => '',
        '91a7' => '',
        '91a8' => '',
        '9129' => '',
        '912a' => '',
        '91ab' => '',
        '912c' => '',
        '91ad' => '',
        '97ae' => '',
        '972f' => '',
        '91ae' => '<$>{italic}<$>',
        '912f' => '<$>{italic}<$>',
        '94a8' => '',
        '9423' => '',
        '94a2' => '',
        '1940' => '',
        '19c1' => '',
        '19c2' => '',
        '1943' => '',
        '19c4' => '',
        '1945' => '',
        '1946' => '',
        '19c7' => '',
        '19c8' => '',
        '1949' => '',
        '194a' => '',
        '19cb' => '',
        '194c' => '',
        '19cd' => '',
        '19ce' => '',
        '194f' => '',
        '19d0' => '',
        '1951' => '',
        '1952' => '',
        '19d3' => '',
        '1954' => '',
        '19d5' => '',
        '19d6' => '',
        '1957' => '',
        '1958' => '',
        '19d9' => '',
        '19da' => '',
        '195b' => '',
        '19dc' => '',
        '195d' => '',
        '195e' => '',
        '19df' => '',
        '19e0' => '',
        '1961' => '',
        '1962' => '',
        '19e3' => '',
        '1964' => '',
        '19e5' => '',
        '19e6' => '',
        '1967' => '',
        '1968' => '',
        '19e9' => '',
        '19ea' => '',
        '196b' => '',
        '19ec' => '',
        '196d' => '',
        '196e' => '',
        '19ef' => '',
        '1970' => '',
        '19f1' => '',
        '19f2' => '',
        '1973' => '',
        '19f4' => '',
        '1975' => '',
        '1976' => '',
        '19f7' => '',
        '19f8' => '',
        '1979' => '',
        '197a' => '',
        '19fb' => '',
        '19fc' => '',
        '19fd' => '',
        '19fe' => '',
        '197f' => '',
        '1a40' => '',
        '1ac1' => '',
        '1ac2' => '',
        '1a43' => '',
        '1ac4' => '',
        '1a45' => '',
        '1a46' => '',
        '1ac7' => '',
        '1ac8' => '',
        '1a49' => '',
        '1a4a' => '',
        '1acb' => '',
        '1a4c' => '',
        '1acd' => '',
        '1ace' => '',
        '1a4f' => '',
        '1ad0' => '',
        '1a51' => '',
        '1a52' => '',
        '1ad3' => '',
        '1a54' => '',
        '1ad5' => '',
        '1ad6' => '',
        '1a57' => '',
        '1a58' => '',
        '1ad9' => '',
        '1ada' => '',
        '1a5b' => '',
        '1adc' => '',
        '1a5d' => '',
        '1a5e' => '',
        '1adf' => '',
        '1ae0' => '',
        '1a61' => '',
        '1a62' => '',
        '1ae3' => '',
        '1a64' => '',
        '1ae5' => '',
        '1ae6' => '',
        '1a67' => '',
        '1a68' => '',
        '1ae9' => '',
        '1aea' => '',
        '1a6b' => '',
        '1aec' => '',
        '1a6d' => '',
        '1a6e' => '',
        '1aef' => '',
        '1a70' => '',
        '1af1' => '',
        '1af2' => '',
        '1a73' => '',
        '1af4' => '',
        '1a75' => '',
        '1a76' => '',
        '1af7' => '',
        '1af8' => '',
        '1a79' => '',
        '1a7a' => '',
        '1afb' => '',
        '1afc' => '',
        '1afd' => '',
        '1afe' => '',
        '1a7f' => '',
        '9d40' => '',
        '9dc1' => '',
        '9dc2' => '',
        '9d43' => '',
        '9dc4' => '',
        '9d45' => '',
        '9d46' => '',
        '9dc7' => '',
        '9dc8' => '',
        '9d49' => '',
        '9d4a' => '',
        '9dcb' => '',
        '9d4c' => '',
        '9dcd' => '',
        '9dce' => '',
        '9d4f' => '',
        '9dd0' => '',
        '9d51' => '',
        '9d52' => '',
        '9dd3' => '',
        '9d54' => '',
        '9dd5' => '',
        '9dd6' => '',
        '9d57' => '',
        '9d58' => '',
        '9dd9' => '',
        '9dda' => '',
        '9d5b' => '',
        '9ddc' => '',
        '9d5d' => '',
        '9d5e' => '',
        '9ddf' => '',
        '9de0' => '',
        '9d61' => '',
        '9d62' => '',
        '9de3' => '',
        '9d64' => '',
        '9de5' => '',
        '9de6' => '',
        '9d67' => '',
        '9d68' => '',
        '9de9' => '',
        '9dea' => '',
        '9d6b' => '',
        '9dec' => '',
        '9d6d' => '',
        '9d6e' => '',
        '9def' => '',
        '9d70' => '',
        '9df1' => '',
        '9df2' => '',
        '9d73' => '',
        '9df4' => '',
        '9d75' => '',
        '9d76' => '',
        '9df7' => '',
        '9df8' => '',
        '9d79' => '',
        '9d7a' => '',
        '9dfb' => '',
        '9dfc' => '',
        '9dfd' => '',
        '9dfe' => '',
        '9d7f' => '',
        '9e40' => '',
        '9ec1' => '',
        '9ec2' => '',
        '9e43' => '',
        '9ec4' => '',
        '9e45' => '',
        '9e46' => '',
        '9ec7' => '',
        '9ec8' => '',
        '9e49' => '',
        '9e4a' => '',
        '9ecb' => '',
        '9e4c' => '',
        '9ecd' => '',
        '9ece' => '',
        '9e4f' => '',
        '9ed0' => '',
        '9e51' => '',
        '9e52' => '',
        '9ed3' => '',
        '9e54' => '',
        '9ed5' => '',
        '9ed6' => '',
        '9e57' => '',
        '9e58' => '',
        '9ed9' => '',
        '9eda' => '',
        '9e5b' => '',
        '9edc' => '',
        '9e5d' => '',
        '9e5e' => '',
        '9edf' => '',
        '9ee0' => '',
        '9e61' => '',
        '9e62' => '',
        '9ee3' => '',
        '9e64' => '',
        '9ee5' => '',
        '9ee6' => '',
        '9e67' => '',
        '9e68' => '',
        '9ee9' => '',
        '9eea' => '',
        '9e6b' => '',
        '9eec' => '',
        '9e6d' => '',
        '9e6e' => '',
        '9eef' => '',
        '9e70' => '',
        '9ef1' => '',
        '9ef2' => '',
        '9e73' => '',
        '9ef4' => '',
        '9e75' => '',
        '9e76' => '',
        '9ef7' => '',
        '9ef8' => '',
        '9e79' => '',
        '9e7a' => '',
        '9efb' => '',
        '9efc' => '',
        '9efd' => '',
        '9efe' => '',
        '9e7f' => '',
        '1f40' => '',
        '1fc1' => '',
        '1fc2' => '',
        '1f43' => '',
        '1fc4' => '',
        '1f45' => '',
        '1f46' => '',
        '1fc7' => '',
        '1fc8' => '',
        '1f49' => '',
        '1f4a' => '',
        '1fcb' => '',
        '1f4c' => '',
        '1fcd' => '',
        '1fce' => '',
        '1f4f' => '',
        '1fd0' => '',
        '1f51' => '',
        '1f52' => '',
        '1fd3' => '',
        '1f54' => '',
        '1fd5' => '',
        '1fd6' => '',
        '1f57' => '',
        '1f58' => '',
        '1fd9' => '',
        '1fda' => '',
        '1f5b' => '',
        '1fdc' => '',
        '1f5d' => '',
        '1f5e' => '',
        '1fdf' => '',
        '1fe0' => '',
        '1f61' => '',
        '1f62' => '',
        '1fe3' => '',
        '1f64' => '',
        '1fe5' => '',
        '1fe6' => '',
        '1f67' => '',
        '1f68' => '',
        '1fe9' => '',
        '1fea' => '',
        '1f6b' => '',
        '1fec' => '',
        '1f6d' => '',
        '1f6e' => '',
        '1fef' => '',
        '1f70' => '',
        '1ff1' => '',
        '1ff2' => '',
        '1f73' => '',
        '1ff4' => '',
        '1f75' => '',
        '1f76' => '',
        '1ff7' => '',
        '1ff8' => '',
        '1f79' => '',
        '1f7a' => '',
        '1ffb' => '',
        '1ffc' => '',
        '1ffd' => '',
        '1ffe' => '',
        '1f7f' => '',
        '9840' => '',
        '98c1' => '',
        '98c2' => '',
        '9843' => '',
        '98c4' => '',
        '9845' => '',
        '9846' => '',
        '98c7' => '',
        '98c8' => '',
        '9849' => '',
        '984a' => '',
        '98cb' => '',
        '984c' => '',
        '98cd' => '',
        '98ce' => '',
        '984f' => '',
        '98d0' => '',
        '9851' => '',
        '9852' => '',
        '98d3' => '',
        '9854' => '',
        '98d5' => '',
        '98d6' => '',
        '9857' => '',
        '9858' => '',
        '98d9' => '',
        '98da' => '',
        '985b' => '',
        '98dc' => '',
        '985d' => '',
        '985e' => '',
        '98df' => '',
        '9b40' => '',
        '9bc1' => '',
        '9bc2' => '',
        '9b43' => '',
        '9bc4' => '',
        '9b45' => '',
        '9b46' => '',
        '9bc7' => '',
        '9bc8' => '',
        '9b49' => '',
        '9b4a' => '',
        '9bcb' => '',
        '9b4c' => '',
        '9bcd' => '',
        '9bce' => '',
        '9b4f' => '',
        '9bd0' => '',
        '9b51' => '',
        '9b52' => '',
        '9bd3' => '',
        '9b54' => '',
        '9bd5' => '',
        '9bd6' => '',
        '9b57' => '',
        '9b58' => '',
        '9bd9' => '',
        '9bda' => '',
        '9b5b' => '',
        '9bdc' => '',
        '9b5d' => '',
        '9b5e' => '',
        '9bdf' => '',
        '9be0' => '',
        '9b61' => '',
        '9b62' => '',
        '9be3' => '',
        '9b64' => '',
        '9be5' => '',
        '9be6' => '',
        '9b67' => '',
        '9b68' => '',
        '9be9' => '',
        '9bea' => '',
        '9b6b' => '',
        '9bec' => '',
        '9b6d' => '',
        '9b6e' => '',
        '9bef' => '',
        '9b70' => '',
        '9bf1' => '',
        '9bf2' => '',
        '9b73' => '',
        '9bf4' => '',
        '9b75' => '',
        '9b76' => '',
        '9bf7' => '',
        '9bf8' => '',
        '9b79' => '',
        '9b7a' => '',
        '9bfb' => '',
        '9bfc' => '',
        '9bfd' => '',
        '9bfe' => '',
        '9b7f' => '',
        '1c40' => '',
        '1cc1' => '',
        '1cc2' => '',
        '1c43' => '',
        '1cc4' => '',
        '1c45' => '',
        '1c46' => '',
        '1cc7' => '',
        '1cc8' => '',
        '1c49' => '',
        '1c4a' => '',
        '1ccb' => '',
        '1c4c' => '',
        '1ccd' => '',
        '1cce' => '',
        '1c4f' => '',
        '1cd0' => '',
        '1c51' => '',
        '1c52' => '',
        '1cd3' => '',
        '1c54' => '',
        '1cd5' => '',
        '1cd6' => '',
        '1c57' => '',
        '1c58' => '',
        '1cd9' => '',
        '1cda' => '',
        '1c5b' => '',
        '1cdc' => '',
        '1c5d' => '',
        '1c5e' => '',
        '1cdf' => '',
        '1ce0' => '',
        '1c61' => '',
        '1c62' => '',
        '1ce3' => '',
        '1c64' => '',
        '1ce5' => '',
        '1ce6' => '',
        '1c67' => '',
        '1c68' => '',
        '1ce9' => '',
        '1cea' => '',
        '1c6b' => '',
        '1cec' => '',
        '1c6d' => '',
        '1c6e' => '',
        '1cef' => '',
        '1c70' => '',
        '1cf1' => '',
        '1cf2' => '',
        '1c73' => '',
        '1cf4' => '',
        '1c75' => '',
        '1c76' => '',
        '1cf7' => '',
        '1cf8' => '',
        '1c79' => '',
        '1c7a' => '',
        '1cfb' => '',
        '1cfc' => '',
        '1cfd' => '',
        '1cfe' => '',
        '1c7f' => '',

        // codes that I don't know what they do
        '947c' => '',
        '137c' => '',
        '8094' => '',
        '7094' => '',
        '917c' => '',
        '8380' => '',
        '0137' => '',
        '1fad' => '',
        '167c' => '',
    ];

    private static $characters = [
        '20' => ' ',
        'a1' => '!',
        'a2' => '"',
        '23' => '#',
        'a4' => '$',
        '25' => '%',
        '26' => '&',
        'a7' => "'",
        'a8' => '(',
        '29' => ')',
        '2a' => 'á',
        'ab' => '+',
        '2c' => ',',
        'ad' => '-',
        'ae' => '.',
        '2f' => '/',
        'b0' => '0',
        '31' => '1',
        '32' => '2',
        'b3' => '3',
        '34' => '4',
        'b5' => '5',
        'b6' => '6',
        '37' => '7',
        '38' => '8',
        'b9' => '9',
        'ba' => ':',
        '3b' => ';',
        'bc' => '<',
        '3d' => '=',
        '3e' => '>',
        'bf' => '?',
        '40' => '@',
        'c1' => 'A',
        'c2' => 'B',
        '43' => 'C',
        'c4' => 'D',
        '45' => 'E',
        '46' => 'F',
        'c7' => 'G',
        'c8' => 'H',
        '49' => 'I',
        '4a' => 'J',
        'cb' => 'K',
        '4c' => 'L',
        'cd' => 'M',
        'ce' => 'N',
        '4f' => 'O',
        'd0' => 'P',
        '51' => 'Q',
        '52' => 'R',
        'd3' => 'S',
        '54' => 'T',
        'd5' => 'U',
        'd6' => 'V',
        '57' => 'W',
        '58' => 'X',
        'd9' => 'Y',
        'da' => 'Z',
        '5b' => '[',
        'dc' => 'é',
        '5d' => ']',
        '5e' => 'í',
        'df' => 'ó',
        'e0' => 'ú',
        '61' => 'a',
        '62' => 'b',
        'e3' => 'c',
        '64' => 'd',
        'e5' => 'e',
        'e6' => 'f',
        '67' => 'g',
        '68' => 'h',
        'e9' => 'i',
        'ea' => 'j',
        '6b' => 'k',
        'ec' => 'l',
        '6d' => 'm',
        '6e' => 'n',
        'ef' => 'o',
        '70' => 'p',
        'f1' => 'q',
        'f2' => 'r',
        '73' => 's',
        'f4' => 't',
        '75' => 'u',
        '76' => 'v',
        'f7' => 'w',
        'f8' => 'x',
        '79' => 'y',
        '7a' => 'z',
        'fb' => 'ç',
        '7c' => '÷',
        'fd' => 'Ñ',
        'fe' => 'ñ',
        '7f' => '',
        '80' => '',
    ];

    private static $special_chars = [
        '91b0' => '®',
        '9131' => '°',
        '9132' => '½',
        '91b3' => '¿',
        '9134' => '™',
        '91b5' => '¢',
        '91b6' => '£',
        '9137' => '♪',
        '9138' => 'à',
        '91b9' => ' ',
        '91ba' => 'è',
        '913b' => 'â',
        '91bc' => 'ê',
        '913d' => 'î',
        '913e' => 'ô',
        '91bf' => 'û',
    ];

    private static $extended_chars2 = [
        [
            'code' => '9220',
            'prefix' => 'A',
            'letter' => 'Á',
        ], [
            'code' => '92a1',
            'prefix' => 'E',
            'letter' => 'É',
        ], [
            'code' => '92a2',
            'prefix' => 'O',
            'letter' => 'Ó',
        ], [
            'code' => '9223',
            'prefix' => 'U',
            'letter' => 'Ú',
        ], [
            'code' => '92a4',
            'prefix' => 'U',
            'letter' => 'Ü',
        ], [
            'code' => '9225',
            'prefix' => 'u',
            'letter' => 'ü',
        ], [
            'code' => '9226',
            'prefix' => "'",
            'letter' => '‘',
        ], [
            'code' => '92a7',
            'prefix' => '!',
            'letter' => '¡',
        ], [
            'code' => '92a8',
            'prefix' => '#',
            'letter' => '*',
        ], [
            'code' => '9229',
            'prefix' => "'",
            'letter' => '’',
        ], [
            'code' => '922a',
            'prefix' => '-',
            'letter' => '—',
        ], [
            'code' => '92ab',
            'prefix' => 'c',
            'letter' => '©',
        ], [
            'code' => '922c',
            'prefix' => 's',
            'letter' => '℠',
        ], [
            'code' => '92ad',
            'prefix' => '.',
            'letter' => '•',
        ], [
            'code' => '92ae',
            'prefix' => '"',
            'letter' => '“',
        ], [
            'code' => '922f',
            'prefix' => '"',
            'letter' => '”',
        ], [
            'code' => '92b0',
            'prefix' => 'A',
            'letter' => 'À',
        ], [
            'code' => '9231',
            'prefix' => 'A',
            'letter' => 'Â',
        ], [
            'code' => '9232',
            'prefix' => 'C',
            'letter' => 'Ç',
        ], [
            'code' => '92b3',
            'prefix' => 'E',
            'letter' => 'È',
        ], [
            'code' => '9234',
            'prefix' => 'E',
            'letter' => 'Ê',
        ], [
            'code' => '92b5',
            'prefix' => 'E',
            'letter' => 'Ë',
        ], [
            'code' => '92b6',
            'prefix' => 'e',
            'letter' => 'ë',
        ], [
            'code' => '9237',
            'prefix' => 'I',
            'letter' => 'Î',
        ], [
            'code' => '9238',
            'prefix' => 'I',
            'letter' => 'Ï',
        ], [
            'code' => '92b9',
            'prefix' => 'i',
            'letter' => 'ï',
        ], [
            'code' => '92ba',
            'prefix' => 'O',
            'letter' => 'Ô',
        ], [
            'code' => '923b',
            'prefix' => 'U',
            'letter' => 'Ù',
        ], [
            'code' => '92bc',
            'prefix' => 'u',
            'letter' => 'ù',
        ], [
            'code' => '923d',
            'prefix' => 'U',
            'letter' => 'Û',
        ], [
            'code' => '923e',
            'prefix' => '"',
            'letter' => '«',
        ], [
            'code' => '92bf',
            'prefix' => '"',
            'letter' => '»',
        ], [
            'code' => '1320',
            'prefix' => 'A',
            'letter' => 'Ã',
        ], [
            'code' => '13a1',
            'prefix' => 'a',
            'letter' => 'ã',
        ], [
            'code' => '13a2',
            'prefix' => 'I',
            'letter' => 'Í',
        ], [
            'code' => '1323',
            'prefix' => 'I',
            'letter' => 'Ì',
        ], [
            'code' => '13a4',
            'prefix' => 'i',
            'letter' => 'ì',
        ], [
            'code' => '1325',
            'prefix' => 'O',
            'letter' => 'Ò',
        ], [
            'code' => '1326',
            'prefix' => 'o',
            'letter' => 'ò',
        ], [
            'code' => '13a7',
            'prefix' => 'O',
            'letter' => 'Õ',
        ], [
            'code' => '13a8',
            'prefix' => 'o',
            'letter' => 'õ',
        ], [
            'code' => '1329',
            'prefix' => '[',
            'letter' => '{',
        ], [
            'code' => '132a',
            'prefix' => ']',
            'letter' => '}',
        ], [
            'code' => '13ab',
            'prefix' => '/',
            'letter' => '\\',
        ], [
            'code' => '132c',
            'prefix' => '/',
            'letter' => '^',
        ], [
            'code' => '13ad',
            'prefix' => '-',
            'letter' => '_',
        ], [
            'code' => '13ae',
            'prefix' => '-',
            'letter' => '¦',
        ], [
            'code' => '132f',
            'prefix' => '-',
            'letter' => '~',
        ], [
            'code' => '13b0',
            'prefix' => 'A',
            'letter' => 'Ä',
        ], [
            'code' => '1331',
            'prefix' => 'a',
            'letter' => 'ä',
        ], [
            'code' => '1332',
            'prefix' => 'O',
            'letter' => 'Ö',
        ], [
            'code' => '13b3',
            'prefix' => 'o',
            'letter' => 'ö',
        ], [
            'code' => '1334',
            'prefix' => 's',
            'letter' => 'ß',
        ], [
            'code' => '13b5',
            'prefix' => 'Y',
            'letter' => '¥',
        ], [
            'code' => '13b6',
            'prefix' => 'C',
            'letter' => '¤',
        ], [
            'code' => '1337',
            'prefix' => '/',
            'letter' => '|',
        ], [
            'code' => '1338',
            'prefix' => 'A',
            'letter' => 'Å',
        ], [
            'code' => '13b9',
            'prefix' => 'a',
            'letter' => 'å',
        ], [
            'code' => '13ba',
            'prefix' => 'O',
            'letter' => 'Ø',
        ], [
            'code' => '133b',
            'prefix' => 'o',
            'letter' => 'ø',
        ], [
            'code' => '13bc',
            'prefix' => '+',
            'letter' => '┌',
        ], [
            'code' => '133d',
            'prefix' => '+',
            'letter' => '┐',
        ], [
            'code' => '133e',
            'prefix' => '+',
            'letter' => '└',
        ], [
            'code' => '13bf',
            'prefix' => '+',
            'letter' => '┘',
        ],
    ];
}
