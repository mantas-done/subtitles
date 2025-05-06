<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;

class CsvConverter implements ConverterContract
{
    public static $allowedSeparators = [",", ";", "|", "\t"];

    private static function timeRegex()
    {
        return rtrim(TxtConverter::$time_regexp, '/') . '|(\d+)/';
    }

    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        $csv = self::csvToArray(trim($file_content));

        $count = count($csv);
        if ($count <  2) {
            return false;
        }
        $last_row = $csv[$count - 1];

        // check if each row has the same column count
        $last_row_count = count($last_row);
        foreach ($csv as $row) {
            if (count($row) !== $last_row_count) {
                return false; // this is not a csv file
            }
        }

        $has_timestamp = false;
        foreach ($last_row as $cell) {
            $is_time = (bool)preg_match(self::timeRegex(), $cell);
            $timestamp = preg_replace(self::timeRegex(), '', $cell);
            $only_timestamp_in_cell = trim($timestamp) === '';
            if ($is_time) {
                if ($only_timestamp_in_cell) {
                    $has_timestamp = true;
                    continue;
                } else {
                    return false;
                }
            }
            $is_text = TxtConverter::hasText($cell);
            if ($has_timestamp && $is_text) {
                return true;
            }
        }
        return false;
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $data = self::csvToArray(trim($file_content));

        $start_time_column = null;
        $end_time_column = null;
        $text_column = null;
        $last_row = end($data);
        $column_count = count($last_row);
        $checked_column = 0;
        foreach ($last_row as $k => $column) {
            if (preg_match(self::timeRegex(), $column)) {
                $start_time_column = $k;
                $checked_column = $k;
                break;
            }
        }
        if ($start_time_column !== null) {
            for ($i = $checked_column + 1; $i < $column_count; $i++) {
                $column = $last_row[$i];
                if (TxtConverter::hasText($column)) {
                    break;
                }
                if (preg_match(self::timeRegex(), $column)) {
                    $end_time_column = $i;
                    $checked_column = $i;
                    break;
                }
            }
        }
        for ($i = $checked_column + 1; $i < $column_count; $i++) {
            $column = $last_row[$i];
            if (TxtConverter::hasText($column)) {
                $text_column = $i;
                break;
            }
        }

        if ($text_column === null) {
            throw new UserException('No text (CsvConverter)');
        }

        $data_string = '';
        $found_data = false;
        foreach ($data as $row) {
            if (!$found_data && $start_time_column !== null) {
                $is_start_time = preg_match(self::timeRegex(), $row[$start_time_column]);
                if (!$is_start_time) {
                    continue; // skip few first rows if label or empty
                }
            }
            if (!$found_data && !TxtConverter::hasText($row[$text_column])) {
                continue;
            }
            $found_data = true;

            if ($start_time_column !== null) {
                $start_time = $row[$start_time_column];
                if (is_numeric($start_time)) {
                    $start_time = number_format($start_time, 3, '.', '');
                }
                $data_string .= "\n" . $start_time;
            }
            if ($end_time_column !== null) {
                $end_time = $row[$end_time_column];
                if (is_numeric($end_time)) {
                    $end_time = number_format($end_time, 3, '.', '');
                }
                $data_string .= ' ' . $end_time;
            }
            $data_string .= "\n" . $row[$text_column];
        }

        return (new TxtConverter)->fileContentToInternalFormat($data_string, '');
    }

    /**
     * Convert library's "internal format" (array) to file's content
     *
     * @param array $internal_format    Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        $data = [['Start', 'End', 'Text']];
        foreach ($internal_format as $k => $block) {
            $start = $block['start'];
            $end = $block['end'];
            $text = implode(" ", $block['lines']);

            $data[] = [$start, $end, $text];
        }

        ob_start();
        $fp = fopen('php://output', 'w');
        foreach ($data as $fields) {
            fputcsv($fp, $fields);
        }
        $file_content = ob_get_clean();
        fclose($fp);

        return $file_content;
    }

    private static function csvToArray($content)
    {
        $fp = fopen("php://temp", 'r+');
        fputs($fp, $content);
        rewind($fp);

        $separator = self::detectSeparator($content);
        $csv = [];
        while ( ($data = fgetcsv($fp, 0, $separator, '"', '\\') ) !== false ) {
            $csv[] = $data;
        }
        fclose($fp);

        $csv2 = [];
        foreach ($csv as $row) {
            if ($row[0] == '' && (!isset($row[1]) || trim($row[1]) === '')) { // empty line
                continue;
            }
            $csv2[] = $row;
        }

        return $csv2;
    }

    private static function detectSeparator($file_content)
    {
        $lines = explode("\n", $file_content);
        $results = [];
        foreach ($lines as $line) {
            foreach (self::$allowedSeparators as $delimiter) {
                $count = count(explode($delimiter, $line));
                if ($count < 2) continue; // delimiter not found in line, minimum 2 cols (timestamp + text)

                if (empty($results[$delimiter])) {
                    $results[$delimiter] = [];
                }
                $results[$delimiter][] = $count;
            }
        }

        foreach ($results as $delimiter => $value) {
            $flipped = array_flip($value);
            $results[$delimiter] = max($flipped);
        }

        arsort($results, SORT_NUMERIC);

        return !empty($results) ? key($results) : self::$allowedSeparators[0];
    }
}
