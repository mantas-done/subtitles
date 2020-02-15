<?php namespace Done\Subtitles;

class SrtConverter implements ConverterContract {

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content)
    {
        $internal_format = []; // array - where file content will be stored

        $blocks = explode("\n\n", trim($file_content)); // each block contains: start and end times + text
        foreach ($blocks as $block) {
            preg_match('/(?<start>.*) --> (?<end>.*)\n(?<text>(\n*.*)*)/m', $block, $matches);

            // if block doesn't contain text (invalid srt file given)
            if (empty($matches)) {
                continue;
            }

            $internal_format[] = [
                'start' => static::srtTimeToInternal($matches['start']),
                'end' => static::srtTimeToInternal($matches['end']),
                'lines' => explode("\n", $matches['text']),
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
        $file_content = '';

        foreach ($internal_format as $k => $block) {
            $nr = $k + 1;
            $start = static::internalTimeToSrt($block['start']);
            $end = static::internalTimeToSrt($block['end']);
            $lines = implode("\r\n", $block['lines']);

            $file_content .= $nr . "\r\n";
            $file_content .= $start . ' --> ' . $end . "\r\n";
            $file_content .= $lines . "\r\n";
            $file_content .= "\r\n";
        }

        $file_content = trim($file_content);

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     *
     * @param $srt_time
     *
     * @return float
     */
    protected static function srtTimeToInternal($srt_time)
    {
        $parts = explode(',', $srt_time);

        $only_seconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float)('0.' . $parts[1]);

        $time = $only_seconds + $milliseconds;

        return $time;
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17,440
     *
     * @param float $internal_time
     *
     * @return string
     */
    protected static function internalTimeToSrt($internal_time)
    {
        $parts = explode('.', $internal_time); // 1.23
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : 0; // 23

        $srt_time = gmdate("H:i:s", floor($whole)) . ',' . str_pad($decimal, 3, '0', STR_PAD_RIGHT);

        return $srt_time;
    }
}
