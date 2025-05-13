<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;

class TxtConverter implements ConverterContract
{
    public static $time_regexp = '/(?:\d{2}[:.])(?:\d{2}[:.])(?:\d{2}[:.])(?:\d{2,3})|(?:\d{2}[:;])(?:\d{1,2}[:;])(?:\d{1,2}[:;])\d{1,3}|(?:\d{1,2}[:;])?(?:\d{1,2}[:;])\d{1,3}(?:[.,]\d+)?(?!\d)|\d{1,5}[.,]\d{1,3}/';
    private static $any_letter_regex = '/\p{L}/u';

    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return self::hasText($file_content) && !Helpers::strContains($file_content, "\x00"); // not a binary file
    }

    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        // just text lines
        // timestamps on the same line
        // numbered file
        // timestamps on separate line

        $file_content2 = trim($file_content);
        $file_content2 = preg_replace("/\n+/", "\n", $file_content2);
        $lines = mb_split("\n", $file_content2);
        if ($lines === false) {
            throw new UserException("Can't get lines from this file");
        }

        if (!self::doesFileUseTimestamps($lines)) {
            if (self::areEmptyLinesUsedAsSeparators($file_content)) {
                return self::twoLinesSeparatedByEmptyLine($file_content);
            }

            return self::withoutTimestampsInternalFormat($lines);
        }

        $colon_count = self::detectMostlyUsedTimestampType($lines);
        $fps = 25;
        if ($colon_count === 3) {
            $fps = self::maxFps($lines);
        }
        $timestamp_count = self::timestampCount($lines);

        // line parts to array
        $array = [];
        $seen_first_timestamp = false;
        foreach ($lines as $line) {
            $tmp = self::getLineParts($line, $colon_count, $timestamp_count) + ['line' => $line];
            if ($tmp['start'] !== null) { // only if timestamp format matches add timestamps
                if (substr_count($tmp['start'], ':') >= $colon_count || substr_count($tmp['start'], ';') >= $colon_count) {
                    $tmp['start'] = self::timeToInternal($tmp['start'], $fps);
                    $tmp['end'] = $tmp['end'] != null ? self::timeToInternal($tmp['end'], $fps) : null;
                    $seen_first_timestamp = true;
                } else {
                    $tmp['start'] = null;
                    $tmp['end'] = null;
                    $tmp['text'] = $tmp['line'];
                }
            }
            if (!$seen_first_timestamp) {
                continue;
            }
            $array[] = $tmp;
        }

        // connect timestamps and text from different lines
        $data = [];
        for ($i = 0; $i < count($array); $i++) {
            $row = $array[$i];
            if (!isset($row['text'])) {
                continue;
            }
            if (preg_match('/^[0-9]+$/', $row['text'])) { // only number on the line
                // @phpstan-ignore-next-line
                if (isset($array[$i + 1]['start']) && $array[$i + 1]['start'] !== null) { // timestamp
                    continue; // probably a number from an srt file, because after the number goes the timestamp
                }
            }

            $start = null;
            $end = null;
            if (isset($row['start'])) {
                $start = $row['start'];
                $end = $row['end'] ?? null;
            } elseif (isset($array[$i - 1]['start']) && $array[$i - 1]['text'] === null) {
                $start = $array[$i - 1]['start'];
                $end = $array[$i - 1]['end'] ?? null;
            } elseif (isset($array[$i - 2]['start']) && $array[$i - 2]['text'] === null) {
                $start = $array[$i - 2]['start'];
                $end = $array[$i - 2]['end'] ?? null;
            }

            $data[] = [
                'start' => $start,
                'end' => $end,
                'text' => $row['text'],
            ];
        }

        // merge lines with same timestamps
        $internal_format = [];
        $j = 0;
        foreach ($data as $k => $row) {
            for ($i = 1; $i <= 10; $i++) { // up to 10 lines
                if (
                    isset($data[$k - $i]['start'])
                    && ($data[$k - $i]['start'] === $row['start'] || $row['start'] === null)
                ) {
                    $internal_format[$j - 1]['lines'][] = $row['text'];
                    continue 2;
                }
            }

            $internal_format[$j] =  [
                'start' => $row['start'],
                'end' => $row['end'],
                'lines' => [$row['text']],
            ];
            $j++;
        }

        // strip html
        foreach ($internal_format as &$row) {
            foreach ($row['lines'] as &$line) {
                $line = Helpers::removeOnlyHtmlTags($line);
            }
            unset($line);
        }
        unset($row);

        $internal_format = self::fillStartAndEndTimes($internal_format);
        $internal_format = self::removeRepeatingTextStarts($internal_format);

        return $internal_format;
    }

    // start and end timestamp
    // or just end timestamp
    private static function timestampCount(array $lines): int
    {
        $start_count = 0;
        $end_count = 0;
        foreach ($lines as $line) {
            $timestamps = self::timestampsFromLine($line);
            if ($timestamps['start']) {
                $start_count++;
            }
            if ($timestamps['end']) {
                $end_count++;
            }
        }

        if (self::isDifferenceLessThan10Percent($start_count, $end_count)) {
            return 2;
        } else {
            return 1;
        }
    }

    private static function isDifferenceLessThan10Percent($number1, $number2) {
        $diff = abs($number1 - $number2);
        $threshold = max(abs($number1), abs($number2)) * 0.10;
        return $diff < $threshold;
    }

    public static function detectMostlyUsedTimestampType(array $lines)
    {
        $counts = [-1];
        foreach ($lines as $line) {
            $timestamps = self::timestampsFromLine($line);
            if (!$timestamps['start']) {
                continue;
            }

            $tmp = str_replace(';', ':', $timestamps['start']);
            $count = substr_count($tmp, ':');
            if (!isset($counts[$count])) {
                $counts[$count] = 0;
            }
            $counts[$count]++;
        }
        $max_number = max($counts);
        if ($max_number === 0) {
            return 0;
        }

        foreach ($counts as $count => $number) {
            if ($number >= 0 && $number > ($max_number / 10)) {
                return $count;
            }
        }

        throw new \Exception('no timestamps found');
    }

    private static function maxFps(array $lines): float
    {
        $max_fps = 25;
        foreach ($lines as $line) {
            $timestamps = self::timestampsFromLine($line);
            if (!$timestamps['start']) {
                continue;
            }
            $timestamps['start'] = str_replace(';', ':', $timestamps['start']);
            $parts = explode(':', $timestamps['start']);
            if (count($parts) !== 4) {
                continue;
            }
            $fps = end($parts);
            if ($fps > $max_fps) {
                $max_fps = $fps;
            }

            if (!$timestamps['end']) {
                continue;
            }
            $timestamps['end'] = str_replace(';', ':', $timestamps['end']);
            $parts = explode(':', $timestamps['end']);
            if (count($parts) !== 4) {
                continue;
            }
            $fps = end($parts);
            if ($fps > $max_fps) {
                $max_fps = $fps;
            }
        }

        return $max_fps + 1;
    }

    public static function fillStartAndEndTimes(array $internal_format)
    {
        if (count($internal_format) === 0) {
            throw new UserException("Subtitles were not found in this file");
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

        // sort times
        usort($internal_format, function ($a, $b) {
            if ($a['start'] === $b['start']) {
                return $a['end'] <=> $b['end'];
            }
            return $a['start'] <=> $b['start'];
        });

        // fill ends
        foreach ($internal_format as $k => $row) {
            if (!isset($row['end'])) {
                if (isset($internal_format[$k + 1]['start'])) {
                    $tmp = min($internal_format[$k + 1]['start'], $internal_format[$k]['start'] + 60); // max 60 seconds
                    $internal_format[$k]['end'] = $tmp;
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

    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        $file_content = '';

        foreach ($internal_format as $block) {
            $line = implode("\r\n", $block['lines']);

            $file_content .= $line . "\r\n\r\n";
        }

        return trim($file_content);
    }

    public static function getLineParts($line, $colon_count, $timestamp_count)
    {
        $matches = [
            'start' => null,
            'end' => null,
            'text' => null,
        ];
        $timestamps = self::timestampsFromLine($line);

        // there shouldn't be any text before the timestamp
        // if there is text before it, then it is not a timestamp
        $right_timestamp = '';
        if (isset($timestamps['start']) && (substr_count($timestamps['start'], ':') >= $colon_count || substr_count($timestamps['start'], ';') >= $colon_count)) {
            $text_before_timestamp = substr($line, 0, strpos($line, $timestamps['start']));
            if (!self::hasText($text_before_timestamp)) {
                // start
                $matches['start'] = $timestamps['start'];
                $right_timestamp = $matches['start'];
                if ($timestamp_count === 2 && isset($timestamps['end']) && (substr_count($timestamps['end'], ':') >= $colon_count || substr_count($timestamps['end'], ';') >= $colon_count)) {
                    // end
                    $matches['end'] = $timestamps['end'];
                    $right_timestamp = $matches['end'];
                }
            }
        }

        // check if there is any text after the timestamp
        if ($right_timestamp) {
            $tmp_parts = explode($right_timestamp, $line); // if start and end timestamp are equals
            $right_text = end($tmp_parts); // take text after the end timestamp
            if (self::hasText($right_text) || self::hasDigit($right_text)) {
                $matches['text'] = trim($right_text);
            }
        } else {
            $matches['text'] = $line;
        }

        return $matches;
    }

    /**
     * @param string $time
     * @param int|null $fps
     * @return false|float|int|mixed
     */
    public static function timeToInternal(string $time, $fps)
    {
        $time = trim($time);
        $time = str_replace(';', ':', $time);

        $dot_count = substr_count($time, '.');
        if ($dot_count === 3) {
            $time = str_replace('.', ':', $time);
        }

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
            $milliseconds = $frames / $fps;
            return ($hours * 3600) + ($minutes * 60) + $seconds + $milliseconds;
        } else {
            throw new \InvalidArgumentException("Invalid time format: $time");
        }
    }

    public static function doesFileUseTimestamps(array $lines)
    {
        $not_empty_lines = [];
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $not_empty_lines[] = $line;
            }
        }

        $not_empty_line_count = count($not_empty_lines);
        $lines_with_timestamp_count = 0;
        foreach ($lines as $line) {
            preg_match_all(self::$time_regexp . 'm', $line, $timestamps);
            if (isset($timestamps[0][0])) {
                $start = $timestamps[0][0];
                $before = self::strBefore($line, $start);
                if (self::hasText($before)) {
                    continue;
                }
                $lines_with_timestamp_count++;
            }
        }
        return $lines_with_timestamp_count >= ($not_empty_line_count * 0.2); // if there 20% or more lines with timestamps
    }

    public static function strBefore($subject, $search)
    {
        if ($search === '') {
            return $subject;
        }

        $result = strstr($subject, (string) $search, true);

        return $result === false ? $subject : $result;
    }

    private static function timestampsFromLine(string $line)
    {
        preg_match_all(self::$time_regexp . 'm', $line, $timestamps);
        $result = [
            'start' => null,
            'end' => null,
        ];
        if (isset($timestamps[0][0])) {
            $result['start'] = $timestamps[0][0];
        }
        if (isset($timestamps[0][1])) {
            $result['end'] = $timestamps[0][1];
        }
        if ($result['start']) {
            $text_before_timestamp = substr($line, 0, strpos($line, $result['start']));
            if (self::hasText($text_before_timestamp)) {
                $result = [
                    'start' => null,
                    'end' => null,
                ];
            }
        }

        return $result;
    }

    public static function withoutTimestampsInternalFormat(array $lines)
    {
        $internal_format = [];
        foreach ($lines as $line) {
            $internal_format[] = ['lines' => [$line]];
        }
        $internal_format = self::fillStartAndEndTimes($internal_format);

        // strip html
        foreach ($internal_format as &$row) {
            foreach ($row['lines'] as &$line) {
                $line = Helpers::removeOnlyHtmlTags($line);
            }
            unset($line);
        }
        unset($row);

        return $internal_format;
    }

    private static function areEmptyLinesUsedAsSeparators(string $file_content)
    {
        $counts = self::countLinesWithEmptyLines($file_content);
        return
            $counts['double_text_lines'] > $counts['lines'] * 0.01
            && $counts['single_empty_lines'] > $counts['lines'] * 0.05
        ;
    }

    private static function countLinesWithEmptyLines($file_content) {
        $file_content = trim($file_content);
        $lines = mb_split("\n", $file_content);
        $single_empty_lines = 0;
        $double_text_lines = 0;
        foreach ($lines as &$line) {
            $line = trim($line);
        }
        unset($line);

        foreach ($lines as $k => $line) {
            if ($line === '') {
                continue;
            }

            $last_empty_line = isset($lines[$k - 1]) && $lines[$k - 1] === '';
            $last2_empty_line = isset($lines[$k - 2]) && $lines[$k - 2] === '';

            if (!$last_empty_line && $last2_empty_line) {
                $double_text_lines++;
            }
            if ($last_empty_line && !$last2_empty_line) {
                $single_empty_lines++;
            }
        }

        return [
            'lines' => count($lines),
            'double_text_lines' => $double_text_lines,
            'single_empty_lines' => $single_empty_lines,
        ];
    }

    private static function twoLinesSeparatedByEmptyLine(string $file_content)
    {
        $lines = mb_split("\n", $file_content);
        $internal_format = [];
        $i = 0;
        foreach ($lines as $k => $line) {
            $is_empty = trim($line) === '';
            $last_empty_line = isset($lines[$k - 1]) && trim($lines[$k - 1]) === '';
            if ($is_empty) {
                continue;
            }

            if ($last_empty_line) {
                $internal_format[$i] = ['lines' => [$line]];
                $i++;
            } else {
                if (isset($internal_format[$i - 1])) {
                    $internal_format[$i - 1]['lines'][] = $line;
                } else {
                    $internal_format[$i] = ['lines' => [$line]];
                    $i++;
                }
            }
        }

        // strip html
        foreach ($internal_format as &$row) {
            foreach ($row['lines'] as &$line) {
                $line = Helpers::removeOnlyHtmlTags($line);
            }
            unset($line);
        }
        unset($row);

        return self::fillStartAndEndTimes($internal_format);
    }

    public static function removeRepeatingTextStarts($internal_format)
    {
        if (count($internal_format) <= 2) {
            return $internal_format; // don't try to filter if there are almost no lines
        }

        $repeating_string = '';

        $first_lines = [];
        foreach ($internal_format as $subtitle) {
            $first_lines[] = $subtitle['lines'][0];
        }

        $length = mb_strlen($first_lines[0]);
        for ($i = 0; $i < $length; $i++) {
            $letter = mb_substr($first_lines[0], $i, 1);

            foreach ($first_lines as $line) {
                if (!mb_strlen($line) > $i) {
                    break 2;
                }
                $line_letter = mb_substr($line, $i, 1);
                if ($line_letter !== $letter) {
                    break 2;
                }
            }
            $repeating_string .= $letter;
        }

        $repeating_length = mb_strlen($repeating_string);
        foreach ($internal_format as &$subtitle) {
            $subtitle['lines'][0] = mb_substr($subtitle['lines'][0], $repeating_length);
        }
        unset($subtitle);

        return $internal_format;
    }


    public static function hasText($line)
    {
        return preg_match(self::$any_letter_regex, $line) === 1;
    }

    private static function hasDigit($line)
    {
        return preg_match('/\d/', $line) === 1;
    }
}
