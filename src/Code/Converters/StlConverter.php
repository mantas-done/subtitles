<?php

namespace Done\Subtitles\Code\Converters;

class StlConverter implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return preg_match('/^\d{2}:\d{2}:\d{2}:\d{2}\s,\s\d{2}:\d{2}:\d{2}:\d{2}\s,.+/m', $file_content) === 1;
    }

    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $not_trimmed_lines = explode("\n", $file_content);
        $lines = array_map('trim', $not_trimmed_lines);

        $frames_per_seconds = static::framesPerSecond($lines);

        $internal_format = [];
        foreach ($lines as $line) {
            if (!static::doesLineHaveTimestamp($line)) {
                continue;
            }

            $internal_format[] = [
                'start' => TxtConverter::timeToInternal(static::getStartLine($line), $frames_per_seconds),
                'end' => TxtConverter::timeToInternal(static::getEndLine($line), $frames_per_seconds),
                'lines' => static::getLines($line),
            ];
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        $stl = '';
        foreach ($internal_format as $row) {
            $stl_start = static::toStlTime($row['start']);
            $stl_end = static::toStlTime($row['end']);
            $stt_lines = static::toStlLines($row['lines']);

            $line = "$stl_start , $stl_end , $stt_lines\r\n";
            $stl .= $line;
        }

        return trim($stl);
    }

    // ------------------------- private -------------------------------------------------------------------------------

    protected static function getLines($original_line)
    {
        $parts = explode(',', $original_line);

        // remove first two time elements
        array_shift($parts);
        array_shift($parts);

        $lines_string = implode(',', $parts);
        $not_trimmed_lines = explode('|', $lines_string);
        $lines = array_map('trim', $not_trimmed_lines);

        return $lines;
    }

    protected static function getStartLine($line)
    {
        $parts = explode(',', $line);
        $start_string = trim($parts[0]);


        return $start_string;
    }

    protected static function getEndLine($line)
    {
        $parts = explode(',', $line);
        $end_string = trim($parts[1]);

        return $end_string;
    }

    protected static function returnFramesFromTime($srt_time)
    {
        $parts = explode(':', $srt_time);
        $frames = array_pop($parts);

        return $frames;
    }

    protected static function doesLineHaveTimestamp($line)
    {
        $first_two_symbols = substr($line, 0, 2);

        return is_numeric($first_two_symbols);
    }

    // stl counts frames at the end (25 - 30 frames)
    protected static function toStlTime($internal_time)
    {
        if ($internal_time >= (3600 * 100)) {
            throw new \Exception('conversion function doesnt support more than 99 hours, edit the code ' . $internal_time);
        }

        $milliseconds = $internal_time - (int)$internal_time;
        $frames_unpadded = floor(25 * $milliseconds); // 25 frames

        $hours = floor($internal_time / 3600);
        $minutes = floor(((int)$internal_time % 3600) / 60);
        $remaining_seconds = (int)$internal_time % 60;

        return sprintf("%02d:%02d:%02d:%02d", $hours, $minutes, $remaining_seconds, $frames_unpadded);


    }

    protected static function toStlLines($lines)
    {
        return implode(' | ', $lines);
    }

    protected static function framesPerSecond($lines)
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

    private static function maxFrames($line, $max_frames)
    {
        if (!static::doesLineHaveTimestamp($line)) {
            return $max_frames;
        }

        $frames1 = static::returnFramesFromTime(static::getStartLine($line));
        $frames2 = static::returnFramesFromTime(static::getEndLine($line));

        if ($frames1 > $max_frames) {
            $max_frames = $frames1;
        }
        if ($frames2 > $max_frames) {
            $max_frames = $frames2;
        }

        return $max_frames;
    }

}
