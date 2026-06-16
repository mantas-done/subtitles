<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;

class LrcConverter implements ConverterContract
{
    protected static $regexp = '/\[\s*(\d{2}:\d{2}(?::\d{2})?(?:\.\d{1,3})?)\s*]/';
    protected static $time_offset_regexp = '/\[offset:\s*\+?(-?\d+)\s*]/s';

    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        // only select when there is text after the timestamp
        // do not select files that have timestamp and text somewhere on the other line
        $regex = rtrim(self::$regexp, '/') . ' *[\p{L}]+' . '/s';
        return preg_match($regex, $file_content) === 1;
    }

    public function fileContentToInternalFormat(string $file_content, string $original_file_content, bool $strict): array
    {
        $timestamp_offset = self::timestampsOffset($file_content);
        $lines = explode("\n", $file_content);
        $internal_format = [];
        foreach ($lines as $line) {
            $found = preg_match_all(self::$regexp, $line, $timestamps);
            if ($found === 0) {
                continue;
            }

            $text = str_replace($timestamps[0], '', $line);

            foreach ($timestamps[1] as $timestamp) {
                $internal_format[] = [
                    'start' => static::lrcTimeToInternal($timestamp, $timestamp_offset),
                    'end' => null,
                    'lines' => [trim($text)]
                ];
            }
        }

        usort($internal_format, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        for ($i = 0; $i < count($internal_format); $i++) {
            if (isset($internal_format[$i - 1]) && $internal_format[$i - 1]['end'] === null) {
                $internal_format[$i - 1]['end'] = $internal_format[$i]['start'];
            }
        }

        //TODO: Currently last line's end time is start + 1sec, but it might be calculated differently
        $last_line = count($internal_format) - 1;
        $internal_format[$last_line]['end'] = $internal_format[$last_line]['start'] + 1;

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        $file_content = '';
        foreach ($internal_format as $i => $block) {
            $timestamp = static::internalTimeToLrc($block['start']);

            $text = implode(' ', $block['lines']);
            $file_content .= '[' . $timestamp . '] ' . $text . "\r\n";
        }

        return $file_content;
    }

    protected static function lrcTimeToInternal($lrc_time, $timestamp_offset) : float
    {
        // In .lrc, ':' separates time fields (h:m:s) and '.' is the only fractional-seconds separator
        $parts = explode(':', $lrc_time);
        if (count($parts) === 3) {
            // hh:mm:ss(.xx)
            $hours = (int) $parts[0];
            $minutes = (int) $parts[1];
            $seconds = (float) $parts[2];
            return $hours * 3600 + $minutes * 60 + $seconds - $timestamp_offset;
        } else if (count($parts) === 2) {
            // mm:ss(.xx)
            $minutes = (int) $parts[0];
            $seconds = (float) $parts[1];
            return $minutes * 60 + $seconds - $timestamp_offset;
        }

        throw new UserException("$lrc_time timestamp is not valid in .lrc file");
    }

    protected static function internalTimeToLrc($internal_time) : string
    {
        $whole = (int) floor($internal_time);
        $minutes = (int) floor($whole / 60);
        $seconds = $whole % 60;

        $parts = explode('.', (string) $internal_time);
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : '0';
        $decimal = str_pad($decimal, 2, '0', STR_PAD_RIGHT);

        $lrc_time = sprintf('%02d:%02d.%s', $minutes, $seconds, $decimal);

        return $lrc_time;
    }

    /*
     * Parse timestamps offset value and cast to seconds.
     * [offset:+/- Overall timestamp adjustment in milliseconds, + shifts time up, - shifts down i.e. a positive value causes lyrics to appear sooner, a negative value causes them to appear later]
     */
    protected static function timestampsOffset($content) : float
    {
        $timestamp_offset = 0;
        if (preg_match(self::$time_offset_regexp, $content, $match)) {
            $factor = (int) $match[1] < 0 ? -1 : 1;
            $offset = abs((int) $match[1]);
            $timestamp_offset = ((int) ($offset / 1000) + (float) ('.' . $offset % 1000)) * $factor;
        }

        return $timestamp_offset;
    }

}