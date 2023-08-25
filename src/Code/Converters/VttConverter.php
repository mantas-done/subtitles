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
        $lines = mb_split("\n", $file_content);
        $colon_count = TxtConverter::detectMostlyUsedTimestampType($lines);
        $internal_format = [];
        $i = -1;
        $seen_first_timestamp = false;
        $last_line_was_empty = true;
        foreach ($lines as $line) {
            $parts = TxtConverter::getLineParts($line, $colon_count, 2);
            if ($seen_first_timestamp === false && $parts['start'] && $parts['end']) {
                $seen_first_timestamp = true;
            }
            if (!$seen_first_timestamp) {
                continue;
            }

            if ($parts['start'] && $parts['end']) {
                $i++;
                $internal_format[$i]['start'] = self::vttTimeToInternal($parts['start']);
                $internal_format[$i]['end'] = self::vttTimeToInternal($parts['end']);
                $internal_format[$i]['lines'] = [];
                $internal_format[$i]['speakers'] = [];

                // styles
                preg_match('/((?:\d{1,2}:){1,2}\d{2}\.\d{1,3})\s+-->\s+((?:\d{1,2}:){1,2}\d{2}\.\d{1,3}) *(.*)/', $line, $matches);
                if (isset($matches[3]) && ltrim($matches[3])) {
                    $internal_format[$i]['vtt_cue_settings'] = ltrim($matches[3]);
                }

                // cue
                if (!$last_line_was_empty && isset($internal_format[$i - 1])) {
                    $count = count($internal_format[$i - 1]['lines']);
                    if ($count === 1) {
                        $internal_format[$i - 1]['lines'][0] = '';
                    } else {
                        unset($internal_format[$i - 1]['lines'][$count - 1]);
                    }
                }
            } elseif ($parts['text']) {
                list($linePart, $speakerPart) = self::fixLine($parts['text']);
                $internal_format[$i]['lines'][] = $linePart;
                $internal_format[$i]['speakers'][] = $speakerPart;
            }

            $last_line_was_empty = trim($line) === '';
        }
        // cleanup speakers, for example ['speaker1', null, null] to ['speaker1']
        foreach ($internal_format as $key => $internal_format_parts) {
            if (isset($internal_format_parts['speakers'])) {
                $hasSpeaker = false;
                $emptySpeakersKeys = [];
                // Check speakers if they are null
                foreach ($internal_format_parts['speakers'] as $speakerKey => $speaker) {
                    if ($speaker) {
                        $emptySpeakersKeys = [];
                        $hasSpeaker = true;
                    } else {
                        $emptySpeakersKeys[] = $speakerKey;
                    }
                }
                // Remove speakers key for that time if all speakers are empty or remove empty ones
                if (!$hasSpeaker) {
                    unset($internal_format[$key]['speakers']);
                } elseif ($emptySpeakersKeys) {
                    foreach ($emptySpeakersKeys as $emptySpeakerKey) {
                        unset($internal_format[$key]['speakers'][$emptySpeakerKey]);
                    }
                }
            }
        }
        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format)
    {
        $file_content = "WEBVTT\r\n\r\n";
        foreach ($internal_format as $k => $block) {
            $start = static::internalTimeToVtt($block['start']);
            $end = static::internalTimeToVtt($block['end']);
            $lines_array = [];
            foreach ($block['lines'] as $key => $line) {
                $speaker = '';
                // if speakers is set
                if (isset($block['speakers'][$key]) && $block['speakers'][$key]) {
                    // create <v speaker>Line</v>
                    $speaker = '<v ' . $block['speakers'][$key] . '>';
                    $lines_array[] = $speaker . $line . '</v>';
                } else {
                    $lines_array[] = $line;
                }
            }
            $lines = implode("\r\n", $lines_array);

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
        $corrected_time = str_replace(',', '.', $vtt_time);
        $parts = explode('.', $corrected_time);

        // parts[0] could be mm:ss or hh:mm:ss format -> always use hh:mm:ss
        $parts[0] = substr_count($parts[0], ':') == 2 ? $parts[0] : '00:' . $parts[0];

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

    protected static function fixLine($line)
    {
        $speaker = null;
        // Remove <v speaker>
        if (substr($line, 0, 3) == '<v ') {
            // Remove <v
            $line = substr($line, 3);
            // Get speaker
            $speaker = substr($line, 0, strpos($line, '>'));
            // Remove speaker>
            $line = str_replace($speaker . '>', '', $line);
        }

        // html
        $line = strip_tags($line);

        return array($line, $speaker);
    }
}
