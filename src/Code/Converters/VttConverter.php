<?php

namespace Done\Subtitles\Code\Converters;

class VttConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return preg_match('/^WEBVTT/m', $file_content) === 1;
    }

    public function fileContentToInternalFormat($file_content)
    {
        preg_match_all(
            '/((?:\d{2}:){1,2}\d{2}\.\d{3})\s-->\s((?:\d{2}:){1,2}\d{2}\.\d{3})\n(.*?)(?=(?:\n\n|$))/s',
            $file_content,
            $matches,
            PREG_SET_ORDER
        );

        $internal_format = [];
        foreach ($matches as $match) {
            if (empty($match[3])) continue;

            $lines = explode("\n", $match[3]);
            $lines_array = array_map(static::fixLine(), $lines);
            $internal_format[] = [
                'start' => static::vttTimeToInternal($match[1]),
                'end' => static::vttTimeToInternal($match[2]),
                'lines' => $lines_array,
            ];
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format)
    {
        $file_content = "WEBVTT\r\n\r\n";

        foreach ($internal_format as $k => $block) {
            $start = static::internalTimeToVtt($block['start']);
            $end = static::internalTimeToVtt($block['end']);
            $lines = implode("\r\n", $block['lines']);

            $file_content .= $start . ' --> ' . $end . "\r\n";
            $file_content .= $lines . "\r\n";
            $file_content .= "\r\n";
        }

        $file_content = trim($file_content);

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    protected static function vttTimeToInternal($vtt_time)
    {
        $parts = explode('.', $vtt_time);
        
        // parts[0] could be mm:ss or hh:mm:ss format -> always use hh:mm:ss
        $parts[0] = substr_count($parts[0], ':') == 2 ? $parts[0] : '00:'.$parts[0];

        $only_seconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float)('0.' . $parts[1]);

        $time = $only_seconds + $milliseconds;

        return $time;
    }

    protected static function internalTimeToVtt($internal_time)
    {
        $parts = explode('.', $internal_time); // 1.23
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : 0; // 23

        $srt_time = gmdate("H:i:s", floor($whole)) . '.' . str_pad($decimal, 3, '0', STR_PAD_RIGHT);

        return $srt_time;
    }

    protected static function fixLine()
    {
        return function($line) {
            // speaker
            if (substr($line, 0, 3) == '<v ') {
                $line = substr($line, 3);
                $line = str_replace('>', ': ', $line);
            }

            // html
            $line = strip_tags($line);

            return $line;
        };
    }
}
