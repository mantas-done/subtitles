<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;

class VttConverter implements ConverterContract
{
    protected static $time_regexp = '((?:\d{2}:){1,2}\d{2}\.\d{3})\s+-->\s+((?:\d{2}:){1,2}\d{2}\.\d{3})';

    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        $lines = explode("\n", $file_content);

        return preg_match('/WEBVTT/m', $lines[0]) === 1;
    }

    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $content = self::removeComments($file_content);

        $lines = mb_split("\n", $content);
        $colon_count = 1;
        $internal_format = [];
        $i = -1;
        $seen_first_timestamp = false;
        $last_line_was_empty = true;

        foreach ($lines as $line) {
            $parts = TxtConverter::getLineParts($line, $colon_count, 2);
            if ($seen_first_timestamp === false && $parts['start'] && $parts['end'] && Helpers::strContains($line, '-->')) {
                $seen_first_timestamp = true;
            }
            if (!$seen_first_timestamp) {
                continue;
            }

            if ($parts['start'] && $parts['end'] && Helpers::strContains($line, '-->')) {
                $i++;
                $internal_format[$i]['start'] = self::vttTimeToInternal($parts['start']);
                $internal_format[$i]['end'] = self::vttTimeToInternal($parts['end']);
                $internal_format[$i]['lines'] = [];

                // styles
                preg_match('/((?:\d{1,2}:){1,2}\d{2}\.\d{1,3})\s+-->\s+((?:\d{1,2}:){1,2}\d{2}\.\d{1,3}) *(.*)/', $line, $matches);
                if (isset($matches[3]) && ltrim($matches[3])) {
                    $internal_format[$i]['vtt']['settings'] = ltrim($matches[3]);
                }

                // cue
                if (!$last_line_was_empty && isset($internal_format[$i - 1])) {
                    $count = count($internal_format[$i - 1]['lines']);
                    // @phpstan-ignore-next-line
                    if ($count === 1) {
                        $internal_format[$i - 1]['lines'][0] = '';
                    } else {
                        // @phpstan-ignore-next-line
                        unset($internal_format[$i - 1]['lines'][$count - 1]);
                    }
                }
            } elseif (trim($line) !== '') {
                $text_line = $line;
                // speaker
                $speaker = null;
                if (preg_match('/<v(?: (.*?))?>((?:.*?)<\/v>)/', $text_line, $matches)) {
                    // @phpstan-ignore-next-line
                    $speaker = isset($matches[1]) ? $matches[1] : null;
                    $text_line = $matches[2];
                }

                // html
                $text_line = strip_tags($text_line);

                $internal_format[$i]['lines'][] = $text_line;
                $internal_format[$i]['vtt']['speakers'][] = $speaker;
            }

            // remove if empty speakers array
            if (isset($internal_format[$i]['vtt']['speakers'])) {
                $is_speaker = false;
                foreach ($internal_format[$i]['vtt']['speakers'] as $tmp_speaker) {
                    if ($tmp_speaker !== null) {
                        $is_speaker = true;
                    }
                }
                if (!$is_speaker) {
                    unset($internal_format[$i]['vtt']['speakers']);
                    if (count($internal_format[$i]['vtt']) === 0) {
                        unset($internal_format[$i]['vtt']);
                    }
                }
            }

            $last_line_was_empty = trim($line) === '';
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        $file_content = "WEBVTT\r\n\r\n";

        foreach ($internal_format as $k => $block) {
            $start = static::internalTimeToVtt($block['start']);
            $end = static::internalTimeToVtt($block['end']);
            $new_lines = '';
            foreach ($block['lines'] as $line_nr => $line) {
                // @phpstan-ignore-next-line
                if (isset($block['vtt']['speakers'][$line_nr]) && $block['vtt']['speakers'][$line_nr] !== null) {
                    $speaker = $block['vtt']['speakers'][$line_nr];
                    $new_lines .= '<v ' . $speaker . '>' . $line . "</v>\r\n";
                } else {
                    $new_lines .= $line . "\r\n";
                }
            }

            $vtt_settings = '';
            if (isset($block['vtt']['settings'])) {
                $vtt_settings = ' ' . $block['vtt']['settings'];
            }
            $file_content .= $start . ' --> ' . $end . $vtt_settings . "\r\n";
            $file_content .= $new_lines;
            $file_content .= "\r\n";
        }

        $file_content = trim($file_content);

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    protected static function vttTimeToInternal($vtt_time)
    {
        $corrected_time = str_replace(',', '.', $vtt_time);
        $parts = explode('.', $corrected_time);
        
        // parts[0] could be mm:ss or hh:mm:ss format -> always use hh:mm:ss
        $parts[0] = substr_count($parts[0], ':') == 2 ? $parts[0] : '00:'.$parts[0];

        if (!isset($parts[1])) {
            throw new UserException("Invalid timestamp - time doesn't have milliseconds: " . $vtt_time);
        }
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

    protected static function removeComments($content)
    {
        $lines = mb_split("\n", $content);
        $lines = array_map('trim', $lines);
        $new_lines = [];
        $is_comment = false;
        foreach ($lines as $line) {
            if ($is_comment && strlen($line)) {
                continue;
            }
            if (strpos($line, 'NOTE ') === 0) {
                $is_comment = true;
                continue;
            }
            $is_comment = false;
            $new_lines[] = $line;
        }

        $new_content = implode("\n", $new_lines);
        return $new_content;
    }
}
