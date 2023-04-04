<?php

declare(strict_types=1);

namespace Done\Subtitles\Converters;

use function date_parse;
use function explode;
use function floor;
use function gmdate;
use function implode;
use function preg_match;
use function round;
use function str_pad;
use function substr;
use function trim;

use const STR_PAD_LEFT;

// qt.txt
class TxtConverter implements ConverterInterface
{
    private static $fps = 23;

    public function fileContentToInternalFormat(string $fileContent)
    {
        $internal_format = [];

        $blocks = explode("\n\n", trim($fileContent));
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
     * @param array $internalFormat Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internalFormat)
    {
        $file_content = '{QTtext} {font:Tahoma}
{plain} {size:20}
{timeScale:30}
{width:160} {height:32}
{timestamps:absolute} {language:0}
';

        foreach ($internalFormat as $block) {
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

    protected static function timeToInternal(string $srtTime, int $fps)
    {
        $parsed = date_parse("1970-01-01 $srtTime UTC");
        return $parsed['hour'] * 3600 + $parsed['minute'] * 60 + $parsed['second'] + $parsed['fraction'] / $fps * 100;
    }

    protected static function fromInternalTime(string $internalTime, int $fps)
    {
        $parts = explode('.', $internalTime);
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 2) : 0;

        $frame = round($decimal / 100 * 24);

        $srt_time = gmdate("H:i:s", (int) floor($whole)) . '.' . str_pad((string) $frame, 2, '0', STR_PAD_LEFT);

        return "[$srt_time]";
    }
}
