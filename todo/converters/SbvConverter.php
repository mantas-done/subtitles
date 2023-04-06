<?php

declare(strict_types=1);


namespace converters;

use Done\Subtitles\Providers\ConverterInterface;

class SbvConverter implements ConverterInterface
{
    public function parseSubtitles(string $fileContent): array
    {
        $internalFormat = []; // array - where file content will be stored

        $blocks = explode("\n\n", trim($fileContent)); // each block contains: start and end times + text
        foreach ($blocks as $block) {
            $lines = explode("\n", $block); // separate all block lines
            $times = explode(',', $lines[0]); // one the second line there is start and end times

            $internalFormat[] = [
                'start' => static::srtTimeToInternal($times[0]),
                'end' => static::srtTimeToInternal($times[1]),
                'lines' => array_slice($lines, 1), // get all the remaining lines from block (if multiple lines of text)
            ];
        }

        return $internalFormat;
    }

    public function toSubtitles(array $internalFormat): string
    {
        $fileContent = '';

        foreach ($internalFormat as $k => $block) {
            $start = static::internalTimeToSrt($block['start']);
            $end = static::internalTimeToSrt($block['end']);
            $lines = implode("\n", $block['lines']);

            $fileContent .= $start . ',' . $end . "\n";
            $fileContent .= $lines . "\n";
            $fileContent .= "\n";
        }

        $fileContent = trim($fileContent);

        return $fileContent;
    }

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17.440 -> 137.44
     */
    protected static function srtTimeToInternal(string $srtTime): float
    {
        $parts = explode('.', $srtTime);

        $onlySeconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float) '0.' . $parts[1];

        return $onlySeconds + $milliseconds;
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17.440
     */
    protected static function internalTimeToSrt(string $internalTime): string
    {
        $parts = explode('.', $internalTime); // 1.23
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : 0; // 23

        return gmdate("0:i:s", (int) floor($whole)) . '.' . str_pad($decimal, 3, '0', STR_PAD_RIGHT);
    }
}
