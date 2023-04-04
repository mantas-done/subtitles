<?php

declare(strict_types=1);

namespace Done\Subtitles\Converters;

use function explode;
use function floor;
use function gmdate;
use function implode;
use function preg_match;
use function str_pad;
use function strtotime;
use function substr;
use function trim;

use const STR_PAD_RIGHT;

class SrtConverter implements ConverterInterface
{
    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $fileContent Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($fileContent)
    {
        $internalFormat = []; // array - where file content will be stored

        $blocks = explode("\n\n", trim($fileContent)); // each block contains: start and end times + text
        foreach ($blocks as $block) {
            preg_match('/(?<start>.*) --> (?<end>.*)\n(?<text>(\n*.*)*)/m', $block, $matches);

            // if block doesn't contain text (invalid srt file given)
            if (empty($matches)) {
                continue;
            }

            $internalFormat[] = [
                'start' => static::srtTimeToInternal($matches['start']),
                'end' => static::srtTimeToInternal($matches['end']),
                'lines' => explode("\n", $matches['text']),
            ];
        }

        return $internalFormat;
    }

    /**
     * Convert library's "internal format" (array) to file's content
     *
     * @param array $internalFormat Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internalFormat)
    {
        $fileContent = '';

        foreach ($internalFormat as $k => $block) {
            $nr = $k + 1;
            $start = static::internalTimeToSrt($block['start']);
            $end = static::internalTimeToSrt($block['end']);
            $lines = implode("\r\n", $block['lines']);

            $fileContent .= $nr . "\r\n";
            $fileContent .= $start . ' --> ' . $end . "\r\n";
            $fileContent .= $lines . "\r\n";
            $fileContent .= "\r\n";
        }

        $fileContent = trim($fileContent);

        return $fileContent;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     *
     * @param string $srtTime
     * @return float
     */
    protected static function srtTimeToInternal($srtTime)
    {
        $parts = explode(',', $srtTime);

        $onlySeconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float) '0.' . $parts[1];

        return $onlySeconds + $milliseconds;
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17,440
     *
     * @param string $internalTime
     * @return string
     */
    protected static function internalTimeToSrt($internalTime)
    {
        $parts = explode('.', $internalTime); // 1.23
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : 0; // 23

        return gmdate("H:i:s", (int) floor($whole)) . ',' . str_pad($decimal, 3, '0', STR_PAD_RIGHT);
    }
}
