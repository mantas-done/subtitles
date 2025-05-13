<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Helpers;

class SubMicroDvdConverter implements ConverterContract
{
    static protected $fps = 23.976; // SubtitleEdit by default uses this fps. Taken that value without much thinking. Change it to a better values if you will find.


    static $pattern = '/(?:\{|\[)(?<start>\d+)(?:\}|\])(?:\{|\[)(?<end>\d+)(?:\}|\])(?<text>.+)/';
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return preg_match(self::$pattern, $file_content, $matches);
    }

    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $fps = self::$fps;

        preg_match_all(self::$pattern, $file_content, $blocks, PREG_SET_ORDER);

        $internal_format = [];
        foreach ($blocks as $k => $block) {
            if ($k === 0 && is_numeric($block['text'])) {
                $fps = $block['text'];
                continue;
            }

            $lines = explode("|", $block['text']);
            foreach ($lines as &$line) {
                $line = Helpers::strAfterLast($line, '}');
            }
            unset($line);

            $internal_format[] = [
                'start' => static::timeToInternal($block['start'], $fps),
                'end' => static::timeToInternal($block['end'], $fps),
                'lines' => $lines,
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
    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        $file_content = '';

        foreach ($internal_format as $k => $block) {
            $start = static::internalTimeToSub($block['start']);
            $end = static::internalTimeToSub($block['end']);
            $lines = implode("|", $block['lines']);

            $file_content .= '{' . $start . '}{' . $end . '}' . $lines . "\r\n";
        }

        return trim($file_content);
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     *
     * @param $sub_time
     *
     * @return float
     */
    protected static function timeToInternal($sub_time, $fps)
    {
        return $sub_time / $fps;
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
        return (string)($internal_time * self::$fps);
    }
}
