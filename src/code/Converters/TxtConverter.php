<?php namespace Done\Subtitles;

// qt.txt
class TxtConverter implements ConverterContract {

    private static $fps = 23;

    public function fileContentToInternalFormat($file_content)
    {
        $internal_format = [];

        $blocks = explode("\n\n", trim($file_content));
        foreach ($blocks as $block) {
            preg_match('/(?<start>\[.{11}\])\n(?<text>[\s\S]+?)(?=\n\[)\n(?<end>\[.{11}\])/m', $block, $matches);

            // if block doesn't contain text
            if (empty($matches)) {
                continue;
            }

            $internal_format[] = [
                'start' => static::timeToInternal($matches['start'], self::$fps),
                'end' => static::timeToInternal($matches['end'], self::$fps),
                'lines' => explode("\n", $matches['text']),
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
        $file_content = '{QTtext} {font:Tahoma}
{plain} {size:20}
{timeScale:30}
{width:160} {height:32}
{timestamps:absolute} {language:0}
';

        foreach ($internal_format as $block) {
            $start = static::fromInternalTime($block['start'], self::$fps);
            $end = static::fromInternalTime($block['end'], self::$fps);
            $lines = implode("\r\n", $block['lines']);

            $file_content .= $start . "\r\n";
            $file_content .= $lines . "\r\n";
            $file_content .= $end . "\r\n";
            $file_content .= "\r\n";
        }

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    protected static function timeToInternal($srt_time, $fps)
    {
        $parsed = date_parse("1970-01-01 $srt_time UTC");
        $time = $parsed['hour'] * 3600 + $parsed['minute'] * 60 + $parsed['second'] + $parsed['fraction'] / $fps * 100;

        return $time;
    }

    protected static function fromInternalTime($internal_time, $fps)
    {
        $parts = explode('.', $internal_time);
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 2) : 0;

        $frame = round($decimal / 100 * 24);

        $srt_time = gmdate("H:i:s", floor($whole)) . '.' . str_pad($frame, 2, '0', STR_PAD_LEFT);

        return "[$srt_time]";
    }
}
