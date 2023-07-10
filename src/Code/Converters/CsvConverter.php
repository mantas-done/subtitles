<?php

namespace Done\Subtitles\Code\Converters;

class CsvConverter implements ConverterContract
{
    public static $allowedSeparators = [",", ";", "|", "\t"];

    public function canParseFileContent($file_content)
    {
        $csv = self::csvToArray($file_content);

        if (!isset($csv[1][0])) {
            return false;
        }
        $cell = $csv[1][0];
        $timestamp = preg_replace(TxtConverter::$time_regexp, '', $cell);
        $only_timestamp_on_first_column = trim($timestamp) === '';
        return count($csv) >= 2 && $only_timestamp_on_first_column; // at least 2 columns: timestamp + text
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content)
    {
        $data = self::csvToArray($file_content);

        $internal_format = [];
        foreach ($data as $k => $row) {
            $timestamp = preg_replace(TxtConverter::$time_regexp, '', $row[0]);
            if ($k === 0  && trim($timestamp) !== '') { // heading
                continue;
            }

            $internal_format[] = [
                'start' => TxtConverter::timeToInternal($row[0]),
                'end' => TxtConverter::timeToInternal($row[1]),
                'lines' => mb_split("\n", $row[2]),
            ];
        }

        return $internal_format;
    }

    /**
     * Convert library's "internal format" (array) to file's content
     *
     * @param array $internal_format    Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internal_format)
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
        while ( ($data = fgetcsv($fp, 0, $separator) ) !== false ) {
            $csv[] = $data;
        }
        fclose($fp);

        return $csv;
    }

    private static function detectSeparator($file_content)
    {
        $lines = explode("\n", $file_content);
        $results = [];
        foreach ($lines as $line) {
            foreach (self::$allowedSeparators as $delimiter) {
                $count = count(explode($delimiter, $line));
                if ($count === 1) continue; // delimiter not found in line

                if (empty($results[$delimiter])) {
                    $results[$delimiter] = [];
                }
                $results[$delimiter][] = $count;
            }
        }

        foreach ($results as $delimiter => $value) {
            $flipped = array_flip($value);
            $results[$delimiter] = count($flipped);
        }

        arsort($results, SORT_NUMERIC);

        return !empty($results) ? key($results) : self::$allowedSeparators[0];
    }
}
