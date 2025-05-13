<?php

namespace Done\Subtitles\Code\Converters;

// qt.txt
class TxtQuickTimeConverter implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return preg_match('/{QTtext}/m', $file_content) === 1;
    }

    private static $fps = 23;

    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $internal_format = [];

        $started = false;
        $blocks = explode("\n\n", trim($file_content));
        foreach ($blocks as $block) {
            $lines = explode("\n", $block);
            $tmp = [
                'start' => null,
                'end' => null,
                'lines' => [],
            ];
            foreach ($lines as $line) {
                $parts = TxtConverter::getLineParts($line, 2, 1);
                if ($started === false && $parts['start'] === null) {
                    continue;
                }
                $started = true;
                if ($tmp['start'] === null && $parts['start']) {
                    $tmp['start'] = $parts['start'];
                } elseif ($parts['text'] !== null) {
                    $tmp['lines'][] = $parts['text'];
                } elseif ($tmp['end'] === null && $parts['start']) {
                    $tmp['end'] = $parts['start'];
                }
            }

            if (isset($tmp['lines'][0]) && trim($tmp['lines'][0])) {
                $internal_format[] = [
                    'start' => static::timeToInternal($tmp['start'], self::$fps),
                    'end' => $tmp['end'] ? static::timeToInternal($tmp['end'], self::$fps) : null,
                    'lines' => $tmp['lines'],
                ];
            }
        }

        $internal_format = TxtConverter::fillStartAndEndTimes($internal_format);

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
