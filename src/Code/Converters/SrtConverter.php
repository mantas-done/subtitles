<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;

class SrtConverter implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return preg_match('/^0*\d?\R(\d{1,2}:\d{2}:\d{2}[,\.]\d{1,3}\s*-->\s*\d{1,2}:\d{2}:\d{2}[,\.]\d{1,3})\R(.+)$/m', $file_content) === 1;
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $lines = mb_split("\n", $file_content);
        $internal_format = [];
        $i = -1;
        $saw_start = false;
        foreach ($lines as $k => $line) {
            $parts = TxtConverter::getLineParts($line, 1, 2);

            if ($parts['start'] && $parts['end'] && strpos($line, '->') !== false) {
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
                    // @phpstan-ignore-next-line
                    if ($count === 1) {
                        $internal_format[$i - 1]['lines'][0] = '';
                    } else {
                        // @phpstan-ignore-next-line
                        unset($internal_format[$i - 1]['lines'][$count - 1]);
                    }
                }
                $saw_start = true;
            /*
            } elseif ($parts['start'] && $parts['end'] && strpos($line, '->') === false) {
                throw new UserException("Arrow should looks like this --> for srt format on line: " . $line . ' (SrtConverter)');
            */
            } elseif ($parts['text'] !== null) {
                $internal_format[$i]['lines'][] = Helpers::removeOnlyHtmlTags($line);
            }

            if (!$saw_start) {
                $internal_format = []; // skip words in front of srt subtitle (invalid subtitles)
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
    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
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
        $pattern = '/(?:(?<hours>\d{1,2}):)?(?<minutes>\d{1,2}):(?<seconds>\d{1,2})([:.,](?<milliseconds>\d{1,3}))?/m';
        if (preg_match($pattern, $srt_time, $matches)) {
            // @phpstan-ignore-next-line
            $hours = (isset($matches['hours']) && $matches['hours']) ? $matches['hours'] : 0 ;
            // @phpstan-ignore-next-line
            $minutes = isset($matches['minutes']) ? $matches['minutes'] : 0;
            // @phpstan-ignore-next-line
            $seconds = isset($matches['seconds']) ? $matches['seconds'] : 0;
            $milliseconds = (isset($matches['milliseconds']) && $matches['milliseconds']) ? $matches['milliseconds'] : "000";
        } else {
            throw new UserException("Can't parse timestamp: \"$srt_time\", near: $lines");
        }

        return $hours * 3600 + $minutes * 60 + $seconds + (float)str_pad($milliseconds, 3, "0", STR_PAD_RIGHT) / 1000;
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
        $negative = false;
        if ($internal_time < 0) {
            $negative = true;
            $internal_time = abs($internal_time);
        }
        $internal_time = round($internal_time, 3);

        $hours = floor($internal_time / 3600);
        $minutes = floor(((int)$internal_time % 3600) / 60);
        $remaining_seconds = (int)$internal_time % 60;
        $milliseconds = round(($internal_time - floor($internal_time)) * 1000);

        return ($negative ? '-' : '') . sprintf("%02d:%02d:%02d,%03d", $hours, $minutes, $remaining_seconds, $milliseconds);
    }
}
