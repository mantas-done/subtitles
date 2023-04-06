<?php

declare(strict_types=1);


namespace converters;

use Done\Subtitles\Providers\ConverterInterface;

class StlConverter implements ConverterInterface
{
    public function parseSubtitles(string $fileContent): array
    {
        $notTrimmedLines = explode("\n", $fileContent);
        $lines = array_map('trim', $notTrimmedLines);

        $framesPerSeconds = static::framesPerSecond($lines);

        $internalFormat = [];
        foreach ($lines as $line) {
            if (!static::doesLineHaveTimestamp($line)) {
                continue;
            }

            $internalFormat[] = [
                'start' => static::convertFromSrtTime(static::getStartLine($line), $framesPerSeconds),
                'end' => static::convertFromSrtTime(static::getEndLine($line), $framesPerSeconds),
                'lines' => static::getLines($line),
            ];
        }

        return $internalFormat;
    }

    public function toSubtitles(array $internalFormat): string
    {
        $stl = '';
        foreach ($internalFormat as $row) {
            $stlStart = static::toStlTime($row['start']);
            $stlEnd = static::toStlTime($row['end']);
            $sttLines = static::toStlLines($row['lines']);

            $line = "$stlStart , $stlEnd , $sttLines\r\n";
            $stl .= $line;
        }

        return trim($stl);
    }

    /** private */
    protected static function getLines(string $originalLine): array
    {
        $parts = explode(',', $originalLine);

        // remove first two time elements
        array_shift($parts);
        array_shift($parts);

        $linesString = implode(',', $parts);
        $notTrimmedLines = explode('|', $linesString);

        return array_map('trim', $notTrimmedLines);
    }

    protected static function getStartLine(string $line): string
    {
        $parts = explode(',', $line);

        return trim($parts[0]);
    }

    protected static function getEndLine(string $line): string
    {
        $parts = explode(',', $line);

        return trim($parts[1]);
    }

    protected static function convertFromSrtTime(string $srtTime, int $framesPerSeconds): float
    {
        $parts = explode(':', $srtTime);
        $frames = array_pop($parts);

        $tmpTime = implode(':', $parts); // '21:30:10'
        $onlySeconds = strtotime("1970-01-01 $tmpTime UTC");

        if ($frames > $framesPerSeconds - 1) {
            $frames = $framesPerSeconds - 1;
        }
        $milliseconds = $frames / $framesPerSeconds;

        return $onlySeconds + $milliseconds;
    }

    protected static function returnFramesFromTime(string $srtTime): string
    {
        $parts = explode(':', $srtTime);

        return array_pop($parts);
    }

    protected static function doesLineHaveTimestamp(string $line): bool
    {
        $firstTwoSymbols = substr($line, 0, 2);

        return is_numeric($firstTwoSymbols);
    }

    /** stl counts frames at the end (25 - 30 frames) */
    protected static function toStlTime(float $seconds): string
    {
        if ($seconds >= 86400) {
            throw new Exception('conversion function doesnt support more than 1 day, edit the code');
        }

        $milliseconds = $seconds - (int) $seconds;
        $framesUnpadded = floor(25 * $milliseconds); // 25 frames
        $frames = str_pad((string) $framesUnpadded, 2, '0', STR_PAD_LEFT);

        return gmdate("H:i:s:$frames", (int) $seconds);
    }

    protected static function toStlLines(array $lines): string
    {
        return implode(' | ', $lines);
    }

    protected static function framesPerSecond(array $lines): int
    {
        $maxFrames = 0;
        foreach ($lines as $line) {
            $maxFrames = self::maxFrames($line, $maxFrames);
        }

        if ($maxFrames >= 30) {
            return $maxFrames + 1;
        }
        if ($maxFrames >= 25) {
            return 30;
        }

        return 25;
    }

    private static function maxFrames(string $line, int $maxFrames): int
    {
        if (!static::doesLineHaveTimestamp($line)) {
            return $maxFrames;
        }

        $frames1 = static::returnFramesFromTime(static::getStartLine($line));
        $frames2 = static::returnFramesFromTime(static::getEndLine($line));

        if ($frames1 > $maxFrames) {
            $maxFrames = $frames1;
        }
        if ($frames2 > $maxFrames) {
            $maxFrames = $frames2;
        }

        return $maxFrames;
    }
}
