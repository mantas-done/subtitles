<?php

namespace Done\Subtitles\Code\Converters;

class CsvConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return false; // csv file is not recognized automatically, user must specify explicitly
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content)
    {
        $lines = explode("\n", $file_content);
        $lines = array_map('trim', $lines);

        $data = array();
        foreach ($lines as $line) {
            $data[] = str_getcsv($line);
        }


        $internal_format = [];
        foreach ($data as $row) {
            if ($row[0] === 'Start') { // heading
                continue;
            }

            $internal_format[] = [
                'start' => $row[0],
                'end' => $row[1],
                'lines' => [$row[2]],
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
}
