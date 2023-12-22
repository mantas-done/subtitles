<?php

namespace Done\Subtitles\Code\Converters;

// 32 characters, 4 lines
// every frame transmits 2 bytes of data (two letters)
// : - non drop frame - counts frames (not time)
// ; - drop frame - counts time (not frames)
// non drop frame plays at 29.97 fps, so in one hour there is fewer frames. Non drop frame time in scc 1:00:00:00 = 1:00:03;18 in real time
// 3.6 seconds difference https://sonix.ai/resources/what-is-drop-frame-vs-non-drop-frame-timecode/
// scc time is earlier that srt time, because it needs to account for time it takes to send the text
use Done\Subtitles\Code\UserException;

class SccConverter implements ConverterContract
{
    private static $fps = 29.97;

    public function canParseFileContent($file_content)
    {
        return preg_match('/Scenarist_SCC V1.0/', $file_content) === 1;
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
        preg_match_all('/^(\d{2}:\d{2}:\d{2}[;:]\d{2})\s+(.*)$/m', $file_content, $matches, PREG_SET_ORDER);
        $parsed = [];
        foreach ($matches as $match) {
            $time = $match[1];
            $data = $match[2];

            $parsed[] = [
                'time' => self::sccTimeToInternal($time, self::codesToBytes($data)),
                'lines' => self::sccToLines($data),
            ];
        }

        $internal_format = [];
        $i = 0;
        foreach ($parsed as $j => $row) {
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
    public function internalFormatToFileContent(array $internal_format)
    {
        $file_content = "Scenarist_SCC V1.0\r\n\r\n";

        $last_end_time = 0;
        $last_internal_format = null;
        foreach ($internal_format as $k => $scc) {
            $lines = self::splitLongLines($scc['lines']); // max 32 characters per line, max 4 lines

            $time_available = $scc['start'] - $last_end_time;
            $frames_available = floor(self::$fps * $time_available);
            $blocks_available = ($frames_available - $frames_available % 2) / 2;
            if ($blocks_available < 8) { // 16 - 94ae 94ae 9420 9420 and 942f 942f (start, end)
                unset($internal_format[$k]); // to little time to show something
                continue;
            }

            $codes = self::textToSccText($lines);
            $codes_array = explode(' ', $codes);
            $codes_array = array_slice($codes_array, 0, $blocks_available - 6);
            $codes = implode(' ', $codes_array);
            $full_codes = "94ae 94ae 9420 9420 $codes 942f 942f";
            $frames_to_send = substr_count($full_codes, ' ') + 1;
            $time_to_send = $frames_to_send / self::$fps;
            $file_content .= self::internalTimeToScc($scc['start'] - $time_to_send, 0) . "\t" . $full_codes . "\r\n\r\n";
            if ($last_internal_format !== null && ($frames_to_send + 4) < $frames_available) {
                self::internalTimeToScc($last_internal_format['end'], 0) . "\t" . '942c 942c' . "\r\n\r\n";
            }
            $last_end_time = $scc['start'];
            $last_internal_format = $scc;
        }
        if (isset($scc)) { // stop last caption
            $file_content .= self::internalTimeToScc($scc['end'] - (4 / self::$fps), 0) . "\t" . '942c 942c' . "\r\n\r\n";
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
    public static function sccTimeToInternal($scc_time, $text_bytes)
    {
        $tmp = str_replace(';', ':', $scc_time);
        $parts = explode(':', $tmp);
        $time = $parts[0] * 3600 + $parts[1] * 60 + $parts[2] + $parts[3] / self::$fps;
        $time += ($text_bytes / 2) / self::$fps;

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
    public static function internalTimeToScc($internal_time, $text_bytes)
    {
        $time = $internal_time - ($text_bytes / 2) / self::$fps;
        $parts = explode('.', $time);
        $whole = (int) $parts[0];
        $decimal = isset($parts[1]) ? (float)('0.' . $parts[1]) : 0.0;
        $frame = round($decimal * self::$fps);
        $frame = min($frame, floor(self::$fps)); // max 29

        $srt_time = gmdate("H:i:s", floor($whole)) . ';' . sprintf("%02d", $frame);

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
        $line_output = '';
        foreach ($lines as $k => $line) {
            $line_output .= ' ' . $positions[4 - $count + $k] . ' ' . $positions[4 - $count + $k]; // aligns text to the bottom
            $line_output .= ' ' . self::lineToText($line);
        }
        return trim($line_output);
    }

    public static function splitLongLines($lines)
    {
        $result = array();
        foreach ($lines as $line) {
            while (strlen($line) > 32) {
                $pos = strrpos(substr($line, 0, 32), ' ');
                if ($pos === false || $pos < strlen($line) - 32) {
                    $pos = 32;
                }
                $result[] = substr($line, 0, $pos);
                $line = substr($line, $pos + 1);
            }
            if (!empty($line)) {
                $result[] = $line;
            }
        }

        $result = array_slice($result, 0, 4); // max 4 lines
        return $result;
    }

    protected static function lineToText($line)
    {
        $reversed_characters = array_flip(self::$characters);
//        $reversed_special = array_flip(self::$special_chars);
//        $reversed_extended = array_flip(self::$extended_chars);
        $codes = '';
        $length = mb_strlen($line, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $character = mb_substr($line, $i, 1, 'UTF-8');
            if (isset($reversed_characters[$character])) {
                $codes .= $reversed_characters[$character];

//            } elseif (isset($reversed_special[$character])) {
//                if (strlen($codes) % 4 === 2) {
//                    $codes .= '80'; // fill
//                }
//                $codes .= $reversed_special[$character];
//            } elseif (isset($reversed_extended[$character])) {
//                if (strlen($codes) % 4 === 2) {
//                    $codes .= '80'; // fill
//                }
//                $codes .= $reversed_extended[$character];
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

    public static function shortenLineTextIfTooLong($output, $start, $end, $additional_bytes)
    {
        $blocks = explode(' ', $output);
        $start_frame = (int)ceil($start * self::$fps);
        $end_frame = (int)floor($end * self::$fps);
        $frame_count = $end_frame - $start_frame - $additional_bytes; // 1 byte is transmitted during 1 frame
        if ($frame_count < 0) {
            throw new UserException("There is to little time between $start and $end timestamps to show text", 123);
        }
        $frame_count = $frame_count - ($frame_count % 2); // nearest event number down
        $block_count = $frame_count / 2;
        $new_blocks = array_slice($blocks, 0, $block_count);

        return implode(' ', $new_blocks);
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

    private static function sccToLines($data)
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

            if (isset(self::$extended_chars[$block])) {
                $text .= self::$extended_chars[$block];
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
                throw new \Exception('unknown block: ' . $block);
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

     private static $extended_chars = [
        '9220' => 'Á',
        '92a1' => 'É',
        '92a2' => 'Ó',
        '9223' => 'Ú',
        '92a4' => 'Ü',
        '9225' => 'ü',
        '9226' => '‘',
        '92a7' => '¡',
        '92a8' => '*',
        '9229' => '’',
        '922a' => '—',
        '92ab' => '©',
        '922c' => '℠',
        '92ad' => '•',
        '92ae' => '“',
        '922f' => '”',
        '92b0' => 'À',
        '9231' => 'Â',
        '9232' => 'Ç',
        '92b3' => 'È',
        '9234' => 'Ê',
        '92b5' => 'Ë',
        '92b6' => 'ë',
        '9237' => 'Î',
        '9238' => 'Ï',
        '92b9' => 'ï',
        '92ba' => 'Ô',
        '923b' => 'Ù',
        '92bc' => 'ù',
        '923d' => 'Û',
        '923e' => '«',
        '92bf' => '»',
        '1320' => 'Ã',
        '13a1' => 'ã',
        '13a2' => 'Í',
        '1323' => 'Ì',
        '13a4' => 'ì',
        '1325' => 'Ò',
        '1326' => 'ò',
        '13a7' => 'Õ',
        '13a8' => 'õ',
        '1329' => '{',
        '132a' => '}',
        '13ab' => '\\',
        '132c' => '^',
        '13ad' => '_',
        '13ae' => '¦',
        '132f' => '~',
        '13b0' => 'Ä',
        '1331' => 'ä',
        '1332' => 'Ö',
        '13b3' => 'ö',
        '1334' => 'ß',
        '13b5' => '¥',
        '13b6' => '¤',
        '1337' => '|',
        '1338' => 'Å',
        '13b9' => 'å',
        '13ba' => 'Ø',
        '133b' => 'ø',
        '13bc' => '┌',
        '133d' => '┐',
        '133e' => '└',
        '13bf' => '┘',
    ];
}
