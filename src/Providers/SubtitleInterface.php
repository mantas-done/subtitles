<?php

declare(strict_types=1);

namespace Done\Subtitles\Providers;

interface SubtitleInterface
{
    public static function convert($from_file_path, $to_file_path);

    public static function load($file_name_or_file_content, $extension = null); // load file

    public function save($file_name); // save file

    public function content($format); // output file content (instead of saving to file)

    public function add($start, $end, $text); // add one line or several

    public function remove($from, $till); // delete text from subtitles

    public function shiftTime($seconds, $from = 0, $till = null); // add or subtract some amount of seconds from all times

    public function shiftTimeGradually($seconds_to_shift, $from = 0, $till = null);

    public function getInternalFormat();

    public function setInternalFormat(array $internal_format);
}
