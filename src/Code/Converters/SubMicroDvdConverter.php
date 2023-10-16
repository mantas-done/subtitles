<?php

namespace Done\Subtitles\Code\Converters;

class SubMicroDvdConverter implements ConverterContract
{
    static protected $fps = 23.975; // SubtitleEdit by default uses this fps. Taken that value without much thinking. Change it to a better values if you will find.

    public function canParseFileContent($file_content)
    {
        $pattern = "/\{\d+\}\{\d+\}(.*)\./";
        return preg_match($pattern, $file_content, $matches);
    }

    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
        $pattern = "/\{(\d+)\}\{(\d+)\}(\{.*\})?(.+)/";
        preg_match_all($pattern, $file_content, $blocks, PREG_SET_ORDER);

        $internal_format = [];
        foreach ($blocks as $block) {

            $internal_format[] = [
                'start' => static::timeToInternal($block[1]),
                'end' => static::timeToInternal($block[2]),
                'lines' => explode("|", $block[4]),
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
    protected static function timeToInternal($sub_time)
    {
        return $sub_time / self::$fps;
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
        return $internal_time * self::$fps;
    }
}
