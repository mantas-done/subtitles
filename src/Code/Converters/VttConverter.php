<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\UserException;

class VttConverter implements ConverterContract
{
    protected static $time_regexp = '((?:\d{2}:){1,2}\d{2}\.\d{3})\s+-->\s+((?:\d{2}:){1,2}\d{2}\.\d{3})';

    public function canParseFileContent($file_content)
    {
        $lines = explode("\n", $file_content);

        return preg_match('/WEBVTT/m', $lines[0]) === 1;
    }

    public function fileContentToInternalFormat($file_content)
    {
        $file_content = self::fixExtraNewLines($file_content);
        $block_lines = self::countBlockLines($file_content);
        $blocks = preg_split("/\n{{$block_lines}}/", $file_content);
        $internal_format = [];
        foreach ($blocks as $block) {
            $found = preg_match('/((?:\d{1,2}:){1,2}\d{2}\.\d{1,3})\s+-->\s+((?:\d{1,2}:){1,2}\d{2}\.\d{1,3})(.*?)\n([\s\S]+)/s', $block, $matches);
            if ($found === 0) {
                continue;
            }

            $trimmed_lines = preg_replace("/\n+/", "\n", trim($matches[4]));
            $lines = explode("\n", $trimmed_lines);
            $lines_array = array_map(static::fixLine(), $lines);
            $format = [
                'start' => static::vttTimeToInternal($matches[1]),
                'end' => static::vttTimeToInternal($matches[2]),
                'lines' => $lines_array,
            ];
            if (ltrim($matches[3])) {
                $format['vtt_cue_settings'] = ltrim($matches[3]);
            }
            $internal_format[] = $format;
        }

        if (count($internal_format) === 0) {
            throw new UserException('No valid .vtt subtitles found in this file');
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

            $vtt_cue_settings = '';
            if (isset($block['vtt_cue_settings'])) {
                $vtt_cue_settings = ' ' . $block['vtt_cue_settings'];
            }
            $file_content .= $start . ' --> ' . $end . $vtt_cue_settings . "\r\n";
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

    protected static function countBlockLines($file_content)
    {
        $min_block_lines = 2;
        preg_match_all('/' . self::$time_regexp . '/', $file_content, $matches);

        // file might contain no cues, need at least two cues to determinate lines between blocks;
        if (count($matches[0]) < 2) {
            return $min_block_lines;
        }

        $second_subtitle_timestamp = $matches[0][1];
        preg_match('/(\s+)' . $second_subtitle_timestamp . '/s', $file_content, $matches);
        $block_lines = substr_count($matches[0], "\n");

        return $block_lines < $min_block_lines ? $min_block_lines : $block_lines; // there can not be less than two new line symbols between blocks
    }

    private static function fixExtraNewLines($file_content) {
        $lines = mb_split("\n", $file_content);
        $startCounting = false;
        $newLineCount = 0;

        foreach ($lines as $line) {
            if (strpos($line, '-->') !== false) {
                $startCounting = true;
                continue;
            }

            if ($startCounting) {
                if (trim($line) === '') {
                    $newLineCount++;
                } else {
                    break;
                }
            }
        }

        if ($newLineCount > 0) {
            $lines = $newLineCount + 1;
            $lines_double = $lines * 2;

            $file_content = str_replace(str_repeat("\n", $lines_double), '{REPLACEMENT}', $file_content);
            $file_content = str_replace(str_repeat("\n", $lines), "\n", $file_content);
            $file_content = str_replace('{REPLACEMENT}', "\n\n", $file_content);
        }

        return $file_content;
    }
}
