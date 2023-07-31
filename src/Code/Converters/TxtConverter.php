<?php

namespace Done\Subtitles\Code\Converters;

class TxtConverter implements ConverterContract
{
    public static $time_regexp = '/(?<!\d)(?:\d{2}:)?(?:\d{1,2}:)?(?:\d{1,2}:)\d{1,2}(?:[.,]\d+)?(?!\d)|\d{1,5}[.,]\d{1,3}/';
    private static $any_letter_regex = '/\p{L}/u';

    public function canParseFileContent($file_content)
    {
        return self::hasText($file_content);
    }

    public function fileContentToInternalFormat($file_content)
    {
        return self::contentToInternalFormatBySeparator($file_content);
    }

    public static function contentToInternalFormatBySeparator($file_content)
    {
        $lines = mb_split("\n", $file_content);
        $internal_format = [];
        $has_timestamps = preg_match('/^(?:[^\p{L}\d\n]*(' . trim(self::$time_regexp, '/') . '))/m', $file_content) === 1; // no text before the timestamp
        $i = -1;
        $skip_if_new_line_will_be_digit = true; // skip first digit before timestamp
        foreach ($lines as $line) {
            if ($skip_if_new_line_will_be_digit && preg_match('/^\s*\d+\s*$/', $line) === 1) {
                $skip_if_new_line_will_be_digit = false;
                continue;
            }

            $matches = self::getLineParts($line);

            if ($matches['start'] !== '') {
                if ($has_timestamps) {
                    $i++;
                }
                $internal_format[$i] = [
                    'start' => self::timeToInternal($matches['start']),
                    'lines' => [],
                ];
            }
            if ($matches['end'] !== '') {
                $internal_format[$i]['end'] = self::timeToInternal($matches['end']);
            }
            if ($matches['text'] !== '' && (self::hasText($matches['text']) || self::hasDigit($matches['text']))) {
                if (!$has_timestamps) {
                    $i++;
                }
                $internal_format[$i]['lines'][] = trim($matches['text']);
                $skip_if_new_line_will_be_digit = false;
            } elseif ($matches['text'] == '') { // if empty line
                $skip_if_new_line_will_be_digit = true;
            }
        }
        if (empty($internal_format[$i]['lines'])) { // not text (only the timestamp)
            unset($internal_format[$i]);
        }

        // fill starts
        $last_start = -1;
        foreach ($internal_format as $k => $row) {
            if (!isset($row['start'])) {
                $last_start++;
                $internal_format[$k]['start'] = $last_start;
            } else {
                $last_start = $row['start'];
            }
        }

        // fill ends
        foreach ($internal_format as $k => $row) {
            if (!isset($row['end'])) {
                if (isset($internal_format[$k + 1]['start'])) {
                    $internal_format[$k]['end'] = $internal_format[$k + 1]['start'];
                } else {
                    $internal_format[$k]['end'] = $internal_format[$k]['start'] + 1;
                }
            }
        }
        if (!isset($row['end'])) {
            $internal_format[$k]['end'] = $row['start'] + 1;
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

    public static function getLineParts($line)
    {
        $matches = [
            'start' => '',
            'end' => '',
            'text' => '',
        ];
        preg_match_all(self::$time_regexp . 'm', $line, $timestamps);

        // there shouldn't be any text before the timestamp
        // if there is text before it, then maybe it is not a timestamp
        $right_timestamp = '';
        if (isset($timestamps[0][0])) {
            $text_before_timestamp = substr($line, 0, strpos($line, $timestamps[0][0]));
            if (!self::hasText($text_before_timestamp)) {
                if (isset($timestamps[0][0])) {
                    // start
                    $matches['start'] = $timestamps[0][0];
                    $right_timestamp = $matches['start'];
                }
                if (isset($timestamps[0][1])) {
                    // end
                    $matches['end'] = $timestamps[0][1];
                    $right_timestamp = $matches['end'];
                }
            }
        }

        // check if there is any text after the timestamp
        $right_text = strstr($line, $right_timestamp);
        if ($right_text) {
            $right_text = substr($right_text, strlen($right_timestamp));
        }
        if (self::hasText($right_text) || self::hasDigit($right_text)) {
            $matches['text'] = $right_text;
        }

        return $matches;
    }

    public static function timeToInternal($time)
    {
        $time = trim($time);
        $time_parts = explode(':', $time);
        $total_parts = count($time_parts);

        if ($total_parts === 1) {
            $tmp = (float) str_replace(',', '.', $time_parts[0]);
            return $tmp;
        } elseif ($total_parts === 2) { // minutes:seconds format
            list($minutes, $seconds) = array_map('intval', $time_parts);
            $tmp = (float) str_replace(',', '.', $time_parts[1]);
            $milliseconds = $tmp - floor($tmp);
            return ($minutes * 60) + $seconds + $milliseconds;
        } elseif ($total_parts === 3) { // hours:minutes:seconds,milliseconds format
            list($hours, $minutes, $seconds) = array_map('intval', $time_parts);
            $tmp = (float) str_replace(',', '.', $time_parts[2]);
            $milliseconds = $tmp - floor($tmp);
            return ($hours * 3600) + ($minutes * 60) + $seconds + $milliseconds;
        } elseif ($total_parts === 4) { // hours:minutes:seconds:frames format
            list($hours, $minutes, $seconds, $frames) = array_map('intval', $time_parts);
            $milliseconds = $frames / 25; // 25 frames
            return ($hours * 3600) + ($minutes * 60) + $seconds + $milliseconds;
        } else {
            throw new \InvalidArgumentException("Invalid time format: $time");
        }
    }

    private static function hasTime($line)
    {
        return preg_match(self::$time_regexp, $line) === 1;
    }

    private static function hasText($line)
    {
        return preg_match(self::$any_letter_regex, $line) === 1;
    }

    private static function hasDigit($line)
    {
        return preg_match('/\d/', $line) === 1;
    }
}
