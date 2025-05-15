<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;

class EbuStlReader implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return substr($file_content, 3, 3) === 'STL' && is_numeric(substr($file_content, 6, 2));
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $fps = substr($original_file_content, 6, 2);
        if (!is_numeric($fps)) {
            throw new \Exception('unknown fps: ' . $fps);
        }

        $packets = str_split($original_file_content, 128);

        $internal_format = [];
        foreach ($packets as $subtitlePacket) {

            $tmp = substr($subtitlePacket, 3, 1);
            $tmp = bin2hex($tmp);
            if ($tmp !== 'ff') {
                continue;
            }

            $timestamp_start = substr($subtitlePacket, 5, 4);
            $timestamp_end = substr($subtitlePacket, 9, 4);
            $text = substr($subtitlePacket, 16, 112);
            $text = str_replace(hex2bin('8f'), "", $text);
            $text = str_replace(["\x80", "\x81", "\x84", "\x85"], '', $text);
            $text = str_replace(hex2bin('8a'), "\n", $text);
            $text = self::iso6937ToUtf8($text);

            $internal_format[] = [
                'start' => self::timestampToSeconds($timestamp_start, $fps),
                'end' => self::timestampToSeconds($timestamp_end, $fps),
                'lines' => explode("\n", $text),
            ];
        }

        return $internal_format;
    }

    /** @throws UserException */
    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        throw new UserException('EBU STL writer is not implemented yet');
    }

    // ------------------------------ private --------------------------------------------------------------------------

    private static function timestampToSeconds($format_timestamp, $fps)
    {
        $hours = substr($format_timestamp, 0, 1);
        $minutes = substr($format_timestamp, 1, 1);
        $seconds = substr($format_timestamp, 2, 1);
        $milliseconds = substr($format_timestamp, 3, 1);
        $hours = bin2hex($hours);
        $minutes = bin2hex($minutes);
        $seconds = bin2hex($seconds);
        $milliseconds = bin2hex($milliseconds);
        $hours = hexdec($hours);
        $minutes = hexdec($minutes);
        $seconds = hexdec($seconds);
        $milliseconds = round(hexdec($milliseconds) / $fps * 1000);

//        return "$hours:$minutes:$seconds.$milliseconds";
        return $hours * 3600 + $minutes * 60 + $seconds + $milliseconds / 1000;
    }

    public static function iso6937ToUtf8($iso6937Text)
    {
        // code table from https://en.wikipedia.org/wiki/T.51/ISO/IEC_6937

        $text = $iso6937Text;

        $mappings = [
            "\xA0" => ' ',
            "\xA1" => '¡',
            "\xA2" => '¢',
            "\xA3" => '£',
            "\xA4" => '$',
            "\xA5" => '¥',
            "\xA6" => '#',
            "\xA7" => '§',
            "\xA8" => '¤',
            "\xA9" => '‘',
            "\xAA" => '“',
            "\xAB" => '«',
            "\xAC" => '←',
            "\xAD" => '↑',
            "\xAE" => '→',
            "\xAF" => '↓',
            "\xB0" => '°',
            "\xB1" => '±',
            "\xB2" => '²',
            "\xB3" => '³',
            "\xB4" => '×',
            "\xB5" => 'µ',
            "\xB6" => '¶',
            "\xB7" => '·',
            "\xB8" => '÷',
            "\xB9" => '’',
            "\xBA" => '”',
            "\xBB" => '»',
            "\xBC" => '¼',
            "\xBD" => '½',
            "\xBE" => '¾',
            "\xBF" => '¿',
            "\xD0" => '―',
            "\xD1" => '¹',
            "\xD2" => '®',
            "\xD3" => '©',
            "\xD4" => '™',
            "\xD5" => '♪',
            "\xD6" => '₠',
            "\xD7" => '‰',
            "\xD8" => 'α',
            "\xD9" => '',
            "\xDA" => '',
            "\xDB" => '',
            "\xDC" => '⅛',
            "\xDD" => '⅜',
            "\xDE" => '⅝',
            "\xDF" => '⅞',
            "\xE0" => 'Ω',
            "\xE1" => 'Æ',
            "\xE2" => 'Đ/Ð',
            "\xE3" => 'ª',
            "\xE4" => 'Ħ',
            "\xE5" => '',
            "\xE6" => 'Ĳ',
            "\xE7" => 'Ŀ',
            "\xE8" => 'Ł',
            "\xE9" => 'Ø',
            "\xEA" => 'Œ',
            "\xEB" => 'º',
            "\xEC" => 'Þ',
            "\xED" => 'Ŧ',
            "\xEE" => 'Ŋ',
            "\xEF" => 'ŉ',
            "\xF0" => 'ĸ',
            "\xF1" => 'æ',
            "\xF2" => 'đ',
            "\xF3" => 'ð',
            "\xF4" => 'ħ',
            "\xF5" => 'ı',
            "\xF6" => 'ĳ',
            "\xF7" => 'ŀ',
            "\xF8" => 'ł',
            "\xF9" => 'ø',
            "\xFA" => 'œ',
            "\xFB" => 'ß',
            "\xFC" => 'þ',
            "\xFD" => 'ŧ',
            "\xFE" => 'ŋ',
            "\xFF" => '■',
        ];
        $text = strtr($text, $mappings);

        $text = self::replace($text, "\xC1", 'AEIOUaeiou', 'ÀÈÌÒÙàèìòù');
        $text = self::replace($text, "\xC2", 'ACEILNORSUYZacegilnorsuyz', 'ÁĆÉÍĹŃÓŔŚÚÝŹáćéģíĺńóŕśúýź');
        $text = self::replace($text, "\xC3", 'ACEGHIJOSUWYaceghijosuwy', 'ÂĈÊĜĤÎĴÔŜÛŴŶâĉêĝĥîĵôŝûŵŷ');
        $text = self::replace($text, "\xC4", 'AINOUainou', 'ÃĨÑÕŨãĩñõũ');
        $text = self::replace($text, "\xC5", 'AEIOUaeiou', 'ĀĒĪŌŪāēīōū');
        $text = self::replace($text, "\xC6", 'AGUagu', 'ĂĞŬăğŭ');
        $text = self::replace($text, "\xC7", 'CEGIZcegz', 'ĊĖĠİŻċėġż');
        $text = self::replace($text, "\xC8", 'AEIOUYaeiouy', 'ÄËÏÖÜŸäëïöüÿ');
        $text = self::replace($text, "\xCA", 'AUau', 'ÅŮåů');
        $text = self::replace($text, "\xCB", 'CGKLNRSTcklnrst', 'ÇĢĶĻŅŖŞŢçķļņŗşţ');
        $text = self::replace($text, "\xCD", 'OUou', 'ŐŰőű');
        $text = self::replace($text, "\xCE", 'AEIUaeiu', 'ĄĘĮŲąęįų');
        $text = self::replace($text, "\xCF", 'CDELNRSTZcdelnrstz', 'ČĎĚĽŇŘŠŤŽčďěľňřšťž');

        $utf8 = $text;

        return $utf8;
    }

    public static function replace($text, $x, $y, $z)
    {
        $length = strlen($y);
        for ($i = 0; $i < $length; $i++) {
            $text = str_replace($x . $y[$i], mb_substr($z, $i, 1, 'UTF-8'), $text);
        }

        return $text;
    }
}
