<?php

declare(strict_types=1);

namespace Done\Subtitles\Converters;

use Closure;

use function array_map;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function floor;
use function gmdate;
use function implode;
use function preg_match;
use function preg_replace;
use function str_pad;
use function str_replace;
use function strpos;
use function strtotime;
use function substr;
use function substr_count;
use function trim;

use const STR_PAD_RIGHT;

class VttConverter implements ConverterInterface
{
    public function fileContentToInternalFormat(string $fileContent): array
    {
        $internalFormat = []; // array - where file content will be stored

        $fileContent = preg_replace('/\n\n+/', "\n\n", $fileContent); // replace if there are more than 2 new lines

        $blocks = explode("\n\n", trim($fileContent)); // each block contains: start and end times + text

        foreach ($blocks as $block) {
            if (preg_match('/^WEBVTT.{0,}/', $block, $matches)) {
                continue;
            }

            $lines = explode("\n", $block); // separate all block lines

            if (strpos($lines[0], '-->') === false) { // first line not containing '-->', must be cue id
                unset($lines[0]); // not supporting cue id
                $lines = array_values($lines);
            }

            $times = explode(' --> ', $lines[0]);

            $linesArray = array_map(static::fixLine(), array_slice($lines, 1)); // get all the remaining lines from block (if multiple lines of text)
            if (count($linesArray) === 0) {
                continue;
            }

            $internalFormat[] = [
                'start' => static::vttTimeToInternal($times[0]),
                'end' => static::vttTimeToInternal($times[1]),
                'lines' => $linesArray,
            ];
        }

        return $internalFormat;
    }

    public function internalFormatToFileContent(array $internalFormat): string
    {
        $fileContent = "WEBVTT\r\n\r\n";

        foreach ($internalFormat as $k => $block) {
            $start = static::internalTimeToVtt($block['start']);
            $end = static::internalTimeToVtt($block['end']);
            $lines = implode("\r\n", $block['lines']);

            $fileContent .= $start . ' --> ' . $end . "\r\n";
            $fileContent .= $lines . "\r\n";
            $fileContent .= "\r\n";
        }

        $fileContent = trim($fileContent);

        return $fileContent;
    }

    /** private */
    protected static function vttTimeToInternal(string $vttTime): float
    {
        $parts = explode('.', $vttTime);

    // parts[0] could be mm:ss or hh:mm:ss format -> always use hh:mm:ss
        $parts[0] = substr_count($parts[0], ':') === 2 ? $parts[0] : '00:' . $parts[0];

        $onlySeconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float) '0.' . $parts[1];

        return $onlySeconds + $milliseconds;
    }

    protected static function internalTimeToVtt(string $internalTime): string
    {
        $parts = explode('.', $internalTime); // 1.23
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : 0; // 23

        return gmdate("H:i:s", (int) floor($whole)) . '.' . str_pad($decimal, 3, '0', STR_PAD_RIGHT);
    }

    protected static function fixLine(): Closure
    {
        return function ($line) {
            if (substr($line, 0, 3) === '<v ') {
                $line = substr($line, 3);
                $line = str_replace('>', ' ', $line);
            }

            return $line;
        };
    }
}
