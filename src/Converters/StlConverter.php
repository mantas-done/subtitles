<?php

declare(strict_types=1);

namespace Done\Subtitles\Converters;

use Exception;

use function array_map;
use function array_pop;
use function array_shift;
use function explode;
use function floor;
use function gmdate;
use function implode;
use function is_numeric;
use function str_pad;
use function strtotime;
use function substr;
use function trim;

use const STR_PAD_LEFT;

class StlConverter implements ConverterInterface
{
    public function fileContentToInternalFormat(string $fileContent)
    {
        $not_trimmed_lines = explode("\n", $fileContent);
        $lines = array_map('trim', $not_trimmed_lines);

        $frames_per_seconds = static::framesPerSecond($lines);

        $internal_format = [];
        foreach ($lines as $line) {
            if (!static::doesLineHaveTimestamp($line)) {
                continue;
            }

            $internal_format[] = [
                'start' => static::convertFromSrtTime(static::getStartLine($line), $frames_per_seconds),
                'end' => static::convertFromSrtTime(static::getEndLine($line), $frames_per_seconds),
                'lines' => static::getLines($line),
            ];
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internalFormat)
    {
        $stl = '';
        foreach ($internalFormat as $row) {
            $stl_start = static::toStlTime($row['start']);
            $stl_end = static::toStlTime($row['end']);
            $stt_lines = static::toStlLines($row['lines']);

            $line = "$stl_start , $stl_end , $stt_lines\r\n";
            $stl .= $line;
        }

        return trim($stl);
    }

    // ------------------------- private -------------------------------------------------------------------------------

    protected static function getLines(string $originalLine)
    {
        $parts = explode(',', $originalLine);

        // remove first two time elements
        array_shift($parts);
        array_shift($parts);

        $lines_string = implode(',', $parts);
        $not_trimmed_lines = explode('|', $lines_string);
        return array_map('trim', $not_trimmed_lines);
    }

    protected static function getStartLine(string $line)
    {
        $parts = explode(',', $line);
        return trim($parts[0]);
    }

    protected static function getEndLine(string $line)
    {
        $parts = explode(',', $line);
        return trim($parts[1]);
    }

    protected static function convertFromSrtTime(string $srtTime, int $framesPerSeconds)
    {
        $parts = explode(':', $srtTime);
        $frames = array_pop($parts);

        $tmp_time = implode(':', $parts); // '21:30:10'
        $only_seconds = strtotime("1970-01-01 $tmp_time UTC");

        if ($frames > $framesPerSeconds - 1) {
            $frames = $framesPerSeconds - 1;
        }
        $milliseconds = $frames / $framesPerSeconds;

        return $only_seconds + $milliseconds;
    }

    protected static function returnFramesFromTime(string $srtTime)
    {
        $parts = explode(':', $srtTime);
        return array_pop($parts);
    }

    protected static function doesLineHaveTimestamp(string $line)
    {
        $first_two_symbols = substr($line, 0, 2);

        return is_numeric($first_two_symbols);
    }

    // stl counts frames at the end (25 - 30 frames)
    protected static function toStlTime(float $seconds)
    {
        if ($seconds >= 86400) {
            throw new Exception('conversion function doesnt support more than 1 day, edit the code');
        }

        $milliseconds = $seconds - (int) $seconds;
        $frames_unpadded = floor(25 * $milliseconds); // 25 frames
        $frames = str_pad((string) $frames_unpadded, 2, '0', STR_PAD_LEFT);

        return gmdate("H:i:s:$frames", (int) $seconds);
    }

    protected static function toStlLines(array $lines)
    {
        return implode(' | ', $lines);
    }

    protected static function framesPerSecond(array $lines)
    {
        $max_frames = 0;
        foreach ($lines as $line) {
            $max_frames = self::maxFrames($line, $max_frames);
        }

        if ($max_frames >= 30) {
            return $max_frames + 1;
        }
        if ($max_frames >= 25) {
            return 30;
        }

        return 25;
    }

    private static function maxFrames(string $line, int $maxFrames)
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
