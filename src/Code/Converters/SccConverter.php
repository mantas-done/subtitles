<?php

namespace Done\Subtitles\Code\Converters;

// 32 characters per line per caption (maximum four captions) for a 30 frame broadcast
class SccConverter implements ConverterContract
{
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
    public function fileContentToInternalFormat($file_content)
    {
        throw new \Exception('not implemented');
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

        foreach ($internal_format as $block) {
            $file_content .= self::textToSccLine($block['start'], $block['end'], $block['lines']);
        }

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .scc file format to internal time format (float in seconds)
     * Example: 00:02:17,44 -> 137.44
     *
     * @param $srt_time
     *
     * @return float
     */
    protected static function sccTimeToInternal($srt_time)
    {
        $parts = explode(',', $srt_time);

        $only_seconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float)('0.' . $parts[1]);

        $time = $only_seconds + $milliseconds;

        return $time;
    }

    /**
     * Convert internal time format (float in seconds) to .scc time format
     * Example: 137.44 -> 00:02:17,44
     *
     * @param float $internal_time
     *
     * @return string
     */
    protected static function internalTimeToScc($internal_time)
    {
        $parts = explode('.', $internal_time);
        $whole = (int) $parts[0];
        $decimal = isset($parts[1]) ? (float)('0.' . $parts[1]) : 0.0;
        $frame = round($decimal * 29.97);
        $frame = min($frame, 29); // max 29

        $srt_time = gmdate("H:i:s", floor($whole)) . ':' . sprintf("%02d", $frame);

        return $srt_time;
    }

    // http://www.theneitherworld.com/mcpoodle/SCC_TOOLS/DOCS/SCC_TOOLS.HTML
    // 00:01:14:20 9425 9425 94ad 94ad 9470 9470 d94f d552 20d0 4cc1 4345 2054 4f20 4c45 c152 ce20 c1ce c420 54c1 4ccb
    // line positions, the last one 9470 9470
    // 94d0 above the last one
    // 1370 above the 94d0
    // 13d0 above the 1370
    protected static function textToSccLine($start, $end, $lines)
    {
        $lines = self::splitLongLines($lines);

        $output = self::internalTimeToScc($start) . "\t" . '94ae 94ae 9420 9420';
        $count = count($lines);
        $positions = [
            '13d0', // 4th from the bottom line
            '1370', // 3th from the bottom line
            '94d0', // 2th from the bottom line
            '9470', // bottom line
        ];
        foreach ($lines as $k => $line) {
            $output .= ' ' . $positions[4 - $count + $k] . ' ' . $positions[4 - $count + $k]; // aligns text to the bottom
            $output .= ' ' . self::lineToText($line);
        }
        $output .= ' 942f 942f' . "\r\n\r\n";
        $output .= self::internalTimeToScc($end) . "\t" . '942c 942c' . "\r\n\r\n";

        return $output;
    }

    public static function splitLongLines($lines)
    {
        $result = array();
        foreach ($lines as $line) {
            while (strlen($line) > 32) {
                $pos = strrpos(substr($line, 0, 32), ' ');
                if ($pos === false) {
                    $result[] = substr($line, 0, 32);
                    $line = substr($line, 32);
                } else {
                    $result[] = substr($line, 0, $pos);
                    $line = substr($line, $pos + 1);
                }
            }
            $result[] = $line;
        }
        return $result;
    }

    protected static function lineToText($line)
    {
        $text = '';
        for ($i = 0; $i < strlen($line); $i++) {
            $character = $line[$i];
            $reversed = array_flip(self::$characters);
            if (isset($reversed[$character])) {
                $text .= $reversed[$character];
            } else {
                $text .= $reversed['#']; // no symbol
            }
        }

        if (strlen($line) % 2 === 1) {
            $text .= '80'; // fill
        }

        $text = self::addSpaceAfter4Characters($text);

        return $text;
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

    // from https://github.com/pbs/pycaption/blob/main/pycaption/scc/constants.py
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
        '80' => ''
    ];
}
