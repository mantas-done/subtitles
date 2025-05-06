<?php

namespace Done\Subtitles\Code\Converters;

class SubViewerConverter implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return preg_match('/^(\d{2}:\d{2}:\d{2}\.\d{2}),(\d{2}:\d{2}:\d{2}\.\d{2})\R(.*)$/m', $file_content) === 1;
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $file_content = self::removeStyles($file_content);
        $file_content = str_replace('[br]', "\n", $file_content);

        return (new TxtConverter())->fileContentToInternalFormat($file_content, '');
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
            $start = static::internalTimeToSub($block['start']);
            $end = static::internalTimeToSub($block['end']);
            $lines = implode("[br]", $block['lines']);

            $file_content .= $start . ',' . $end . "\r\n";
            $file_content .= $lines . "\r\n";
            $file_content .= "\r\n";
        }

        $file_content = trim($file_content);

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .sub file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     *
     * @param $sub_time
     *
     * @return float
     */
    protected static function timeToInternal($sub_time)
    {
        $parts = explode('.', $sub_time);

        $only_seconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float)('0.' . $parts[1]);

        $time = $only_seconds + $milliseconds;

        return $time;
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17,440
     *
     * @param float $internal_time
     *
     * @return string
     */
    protected static function internalTimeToSub($internal_time)
    {
        $hours = floor($internal_time / 3600);
        $minutes = floor(((int)$internal_time % 3600) / 60);
        $remaining_seconds = (int)$internal_time % 60;
        $milliseconds = round(($internal_time - floor($internal_time)) * 100);

        return sprintf("%02d:%02d:%02d.%02d", $hours, $minutes, $remaining_seconds, $milliseconds);
    }

    protected static function removeStyles($content)
    {
        $lines = preg_split('/\R/', $content);
        foreach ($lines as $k => $line) {
            if (strpos($line, '[') === 0) {
                unset($lines[$k]);
            }
        }
        return implode("\n", $lines);
    }
}
