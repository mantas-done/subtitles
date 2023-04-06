<?php

declare(strict_types=1);


namespace converters;

use Done\Subtitles\Providers\ConverterInterface;

class SubConverter implements ConverterInterface
{
    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $fileContent Content of file that will be converted
     *
     * @return array                    Internal format
     */
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
                'lines' => explode('[br]', $lines[1]), // get all the remaining lines from block (if multiple lines of text)
            ];
        }

        return $internalFormat;
    }

    /**
     * Convert library's "internal format" (array) to file's content
     *
     * @param array $internalFormat Internal format
     *
     * @return string                   Converted file content
     */
    public function toSubtitles(array $internalFormat): string
    {
        $fileContent = '';

        foreach ($internalFormat as $k => $block) {
            $start = static::internalTimeToSrt($block['start']);
            $end = static::internalTimeToSrt($block['end']);
            $lines = implode("[br]", $block['lines']);

            $fileContent .= $start . ',' . $end . "\n";
            $fileContent .= $lines . "\n";
            $fileContent .= "\n";
        }

        $fileContent = trim($fileContent);

        return $fileContent;
    }

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     */
    protected static function srtTimeToInternal(string $subTime): float
    {
        $parts = explode('.', $subTime);

        $onlySeconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float) '0.' . $parts[1];

        return $onlySeconds + $milliseconds;
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17,440
     */
    protected static function internalTimeToSrt(float $internalTime): string
    {
        $seconds = floor($internalTime);
        $remainder = fmod($internalTime, 1);
        $remainderString = round($remainder, 2) * 100;
        $remainderString = str_pad((string) $remainderString, 2, '0', STR_PAD_RIGHT);

        return gmdate("H:i:s", (int) $seconds) . '.' . $remainderString;
    }
}
