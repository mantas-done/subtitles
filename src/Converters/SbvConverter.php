<?php

declare(strict_types=1);

namespace Done\Subtitles\Converters;

use function array_slice;
use function explode;
use function floor;
use function gmdate;
use function implode;
use function str_pad;
use function strtotime;
use function substr;
use function trim;

use const STR_PAD_RIGHT;

class SbvConverter implements ConverterInterface
{
    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $fileContent Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($fileContent)
    {
        $internal_format = []; // array - where file content will be stored

        $blocks = explode("\n\n", trim($fileContent)); // each block contains: start and end times + text
        foreach ($blocks as $block) {
            $lines = explode("\n", $block); // separate all block lines
            $times = explode(',', $lines[0]); // one the second line there is start and end times

            $internal_format[] = [
                'start' => static::srtTimeToInternal($times[0]),
                'end' => static::srtTimeToInternal($times[1]),
                'lines' => array_slice($lines, 1), // get all the remaining lines from block (if multiple lines of text)
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
        $file_content = '';

        foreach ($internalFormat as $k => $block) {
            $start = static::internalTimeToSrt($block['start']);
            $end = static::internalTimeToSrt($block['end']);
            $lines = implode("\n", $block['lines']);

            $file_content .= $start . ',' . $end . "\n";
            $file_content .= $lines . "\n";
            $file_content .= "\n";
        }

        $file_content = trim($file_content);

        return $file_content;
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
        $parts = explode('.', $srtTime);

        $only_seconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float) '0.' . $parts[1];

        return $only_seconds + $milliseconds;
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

        return gmdate("0:i:s", (int) floor($whole)) . '.' . str_pad($decimal, 3, '0', STR_PAD_RIGHT);
    }
}
