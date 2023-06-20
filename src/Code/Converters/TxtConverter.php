<?php

namespace Done\Subtitles\Code\Converters;

class TxtConverter implements ConverterContract
{
    private static $regex = '/(?:(\b(?:\d{1,2}:)?(?:\d{1,2}:)?\d{1,2}(?:[.,]\d+)?\b)\s*)?(.*)/';

    public function canParseFileContent($file_content)
    {
        return preg_match(self::$regex, $file_content) === 1;
    }

    public function fileContentToInternalFormat($file_content)
    {
        preg_match_all(self::$regex, $file_content, $matches, PREG_SET_ORDER);
        $data = [];
        $i = 0;
        $last_seen_start = 0;
        foreach ($matches as $match) {
            // regex returns every second row empty
            if (trim($match[2]) == '') {
                continue;
            }

            if ($match[1] === '') {
                $time = $last_seen_start;
                $last_seen_start += 1;
            } else {
                $time = self::timeToInternal($match[1]);
            }
            $text = $match[2];

            if (isset($data[$i - 1])) {
                $data[$i - 1]['end'] = $time;
            }
            $data[$i] = [
                'start' => $time,
                'text' => $text,
            ];
            $i++;
        }
        $data[$i - 1]['end'] = $data[$i - 1]['start'] + 1;

        $internal_format = [];
        foreach ($data as $row) {
            $internal_format[] = [
                'start' => $row['start'],
                'end' => $row['end'],
                'lines' => [$row['text']],
            ];
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format)
    {
        $file_content = '';

        foreach ($internal_format as $block) {
            $line = implode(" ", $block['lines']);

            $file_content .= $line . "\r\n";
        }

        return trim($file_content);
    }

    private static function timeToInternal($time)
    {
        $time = trim($time);
        $time_parts = preg_split('/[:,.]/', $time);
        $total_parts = count($time_parts);

        if ($total_parts === 2) { // minutes:seconds format
            $minutes = (int)$time_parts[0];
            $seconds = (int)$time_parts[1];
            return ($minutes * 60) + $seconds;
        } elseif ($total_parts === 3) { // hours:minutes:seconds format
            $hours = (int)$time_parts[0];
            $minutes = (int)$time_parts[1];
            $seconds = (int)$time_parts[2];
            return ($hours * 3600) + ($minutes * 60) + $seconds;
        } elseif ($total_parts === 4) { // hours:minutes:seconds,milliseconds format
            $hours = (int)$time_parts[0];
            $minutes = (int)$time_parts[1];
            $seconds = (int)$time_parts[2];
            $milliseconds = (float)('0.' . $time_parts[3]);
            return ($hours * 3600) + ($minutes * 60) + $seconds + $milliseconds;
        } else {
            throw new \InvalidArgumentException("Invalid time format: $time");
        }
    }

}
