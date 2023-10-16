<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\UserException;

class EbuStlConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return substr($file_content, 3, 3) === 'STL';
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
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
            $text = str_replace(hex2bin('8a'), "\n", $text);

            $internal_format[] = [
                'start' => self::timestampToSeconds($timestamp_start),
                'end' => self::timestampToSeconds($timestamp_end),
                'lines' => explode("\n", $text),
            ];
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
        throw new \Exception('no implemented');
    }

    // ------------------------------ private --------------------------------------------------------------------------

    function timestampToSeconds($format_timestamp)
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
        $milliseconds = round(hexdec($milliseconds) / 30 * 1000);

//        return "$hours:$minutes:$seconds.$milliseconds";
        return $hours * 3600 + $minutes * 60 + $seconds + $milliseconds / 1000;
    }
}
