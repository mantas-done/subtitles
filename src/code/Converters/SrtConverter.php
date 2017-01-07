<?php namespace Done\SubtitleConverter;

class SrtConverter implements ConverterContract {

    public function parse($string)
    {
        return self::srtToInternalFormat($string);
    }

    public function convert($internal_format)
    {
        throw new \Exception('write code here');
    }

    // ------------------------------ private --------------------------------------------------------------------------

    private static function srtToInternalFormat($srt_file) {

//        $lines = [];
//        $handle = fopen($srt_file, "r");
//        if ($handle) {
//            while (($line = fgets($handle)) !== false) {
//                $lines[] = trim($line);
//            }
//
//            fclose($handle);
//        }

        $lines = explode("\n", $srt_file);
        foreach ($lines as &$line) {
            $line = trim($line);
        }
        unset($line);

        $time_lines = [];
        $is_numeric = false;
        foreach ($lines as $k => $line) {

            if (strstr($line, ' --> ') !== false && $is_numeric) {
                $time_lines[] = $k;
            }

            $is_numeric = is_numeric($line);
        }

        if (count($time_lines) == 0) {
            throw new \Exception('couldnt convert file');
        }

        $next_line_is_text = false;
        $stl_i = -1;
        $tmp_lines = [];
        foreach ($lines as $k => $line) {
            if (in_array($k, $time_lines)) { // text line
                $next_line_is_text = true;

                // add time to stl line
                $stl_i++;


                $tmp_lines[$stl_i]['start'] =  self::getStart($line);
                $tmp_lines[$stl_i]['end'] =  self::getEnd($line);
                continue;
            }

            if ($next_line_is_text && strlen($line)) { // text exists
                // add text to stl line

                $tmp_lines[$stl_i]['lines'][] = $line;

            } else {
                $next_line_is_text = false;
            }
        }

        foreach ($tmp_lines as &$line) { // if there is time, but text is left empty
            if (!isset($line['lines'])) {
                $line['lines'] = [''];
            }
        }
        unset($line);

        return $tmp_lines;
    }


    private static function getStart($line)
    {
        $parts = explode('-->', $line);
        $start_string = trim($parts[0]);

        $start = self::convertFromSrtTime($start_string);

        return $start;
    }

    private static function getEnd($line)
    {
        $parts = explode('-->', $line);
        $start_string = trim($parts[1]);

        $start = self::convertFromSrtTime($start_string);

        return $start;
    }

    private static function convertFromSrtTime($srt_time)
    {
        $parts = explode(',', $srt_time);

        $only_seconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float)('0.' . $parts[1]);

        $time = $only_seconds + $milliseconds;

        return $time;
    }
}