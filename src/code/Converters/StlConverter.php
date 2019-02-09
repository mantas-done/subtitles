<?php namespace Done\Subtitles;

class StlConverter implements ConverterContract {

    public function fileContentToInternalFormat($file_content)
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
                'start' => static::convertFromSrtTime(static::getStartLine($line), $frames_per_seconds),
                'end' => static::convertFromSrtTime(static::getEndLine($line), $frames_per_seconds),
                'lines' => static::getLines($line),
            ];
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format)
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

    protected static function convertFromSrtTime($srt_time, $frames_per_seconds)
    {
        $parts = explode(':', $srt_time);
        $frames = array_pop($parts);

        $tmp_time = implode(':', $parts); // '21:30:10'
        $only_seconds = strtotime("1970-01-01 $tmp_time UTC");

        if ($frames > $frames_per_seconds - 1) {
            $frames = $frames_per_seconds - 1;
        }
        $milliseconds = $frames / $frames_per_seconds;

        $seconds = $only_seconds + $milliseconds;

        return $seconds;
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
    protected static function toStlTime($seconds)
    {
        if ($seconds >= 86400) {
            throw new \Exception('conversion function doesnt support more than 1 day, edit the code');
        }

        $milliseconds = $seconds - (int)$seconds;
        $frames_unpadded = floor(25 * $milliseconds); // 25 frames
        $frames = str_pad($frames_unpadded, 2, '0', STR_PAD_LEFT);

        $stl_time = gmdate("H:i:s:$frames", (int)$seconds);

        return $stl_time;
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
