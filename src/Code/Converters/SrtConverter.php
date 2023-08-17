<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\UserException;

class SrtConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return preg_match('/^0*\d?\R(\d{1,2}:\d{2}:\d{2},\d{1,3}\s*-->\s*\d{1,2}:\d{2}:\d{2},\d{1,3})\R(.+)$/m', $file_content) === 1;
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content)
    {
        $lines = mb_split("\n", $file_content);
        $internal_format = [];
        $i = -1;
        foreach ($lines as $k => $line) {
            $parts = TxtConverter::getLineParts($line, 2, 2);

            if ($parts['start'] && $parts['end'] && strpos($line, '-->') !== false) {
                $i++;
                $next_line = '';
                if (isset($lines[$k + 1])) {
                    $next_line = $lines[$k + 1];
                }
                $internal_format[$i]['start'] = self::srtTimeToInternal($parts['start'], $next_line);
                $internal_format[$i]['end'] = self::srtTimeToInternal($parts['end'], $next_line);
                $internal_format[$i]['lines'] = [];


                // remove number before timestamp
                if (isset($internal_format[$i - 1])) {
                    $count = count($internal_format[$i - 1]['lines']);
                    if ($count === 1) {
                        $internal_format[$i - 1]['lines'][0] = '';
                    } else {
                        unset($internal_format[$i - 1]['lines'][$count - 1]);
                    }
                }
            } elseif ($parts['start'] && !$parts['end'] && strpos($line, '-->') !== false) {
                throw new UserException("Something is wrong with timestamps on this line: " . $line);
            } elseif ($parts['text']) {
                $internal_format[$i]['lines'][] = strip_tags($parts['text']);
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
        $file_content = '';

        foreach ($internal_format as $k => $block) {
            $nr = $k + 1;
            $start = static::internalTimeToSrt($block['start']);
            $end = static::internalTimeToSrt($block['end']);
            $lines = implode("\r\n", $block['lines']);

            $file_content .= $nr . "\r\n";
            $file_content .= $start . ' --> ' . $end . "\r\n";
            $file_content .= $lines . "\r\n";
            $file_content .= "\r\n";
        }

        $file_content = trim($file_content);

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     *
     * @param $srt_time
     *
     * @return float
     */
    protected static function srtTimeToInternal($srt_time, string $lines)
    {
        $pattern = '/(\d{1,2}):(\d{2}):(\d{1,2})([:.,](\d{1,3}))?/m';
        if (preg_match($pattern, $srt_time, $matches)) {
            $hours = $matches[1];
            $minutes = $matches[2];
            $seconds = $matches[3];
            $milliseconds = isset($matches[5]) ? $matches[5] : "000";
        } else {
            throw new UserException("Can't parse timestamp: \"$srt_time\", near: $lines");
        }

        return $hours * 3600 + $minutes * 60 + $seconds + str_pad($milliseconds, 3, "0", STR_PAD_RIGHT) / 1000;
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17,440
     *
     * @param float $internal_time
     *
     * @return string
     */
    public static function internalTimeToSrt($internal_time)
    {
        $parts = explode('.', $internal_time); // 1.23
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : 0; // 23

        $srt_time = gmdate("H:i:s", floor($whole)) . ',' . str_pad($decimal, 3, '0', STR_PAD_RIGHT);

        return $srt_time;
    }
}
