<?php

namespace Done\Subtitles\Code;

use Done\Subtitles\Code\Converters\ConverterContract;
use Done\Subtitles\Subtitles;

class Helpers
{
    public static function shouldBlockTimeBeShifted($from, $till, $block_start, $block_end)
    {
        if ($block_end < $from) {
            return false;
        }

        if ($till === null) {
            return true;
        }

        return $till >= $block_start;
    }

    public static function removeUtf8Bom($text)
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);

        return $text;
    }

    public static function getConverterByExtension($extension)
    {
        foreach (Subtitles::$formats as $row) {
            if ($row['extension'] === $extension) {
                $full_class_name = $row['class'];
                return new $full_class_name();
            }
        }

        throw new \Exception('unknown format: ' . $extension);
    }

    public static function getConverterByFormat($format)
    {
        foreach (Subtitles::$formats as $row) {
            if ($row['format'] === $format) {
                $full_class_name = $row['class'];
                /** @var ConverterContract $converter */
                $converter = new $full_class_name();
                return $converter;
            }
        }

        throw new \Exception("Can't find suitable converter, for format: $format");
    }

    public static function getConverterByFileContent($file_content)
    {
        foreach (Subtitles::$formats as $row) {
            $class_name = $row['class'];
            $full_class_name = $class_name;
            /** @var ConverterContract $converter */
            $converter = new $full_class_name();
            if ($converter->canParseFileContent($file_content)) {
                return $converter;
            }
        }

        throw new \Exception("Can't find suitable converter");
    }

    public static function fileExtension($filename) {
        $parts = explode('.', $filename);
        $extension = end($parts);
        $extension = strtolower($extension);

        return $extension;
    }

    public static function normalizeNewLines($file_content)
    {
        $file_content = str_replace("\r\n", "\n", $file_content);
        $file_content = str_replace("\r", "\n", $file_content);

        return $file_content;
    }

    public static function shiftBlockTime($block, $seconds, $from, $till)
    {
        if (!static::blockTimesWithinRange($block, $from, $till)) {
            return $block;
        }

        // start
        $tmp_from_start = $block['start'] - $from;
        $start_percents = $tmp_from_start / ($till - $from);
        $block['start'] += $seconds * $start_percents;

        // end
        $tmp_from_start = $block['end'] - $from;
        $end_percents = $tmp_from_start / ($till - $from);
        $block['end'] += $seconds * $end_percents;

        return $block;
    }

    public static function blockTimesWithinRange($block, $from, $till)
    {
        return ($from <= $block['start'] && $block['start'] <= $till && $from <= $block['end'] && $block['end'] <= $till);
    }
}
