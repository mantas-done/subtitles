<?php

declare(strict_types=1);

namespace Done\Subtitles\Providers;

interface SubtitleInterface
{
    public static function convert(string $fromFilePath, string $toFilePath);

    public static function load(string $fileNameOrFileContent, ?string $extension = null); // load file

    public function save(string $fileName); // save file

    public function content(string $format); // output file content (instead of saving to file)

    /** @param string|array|mixed $text */
    public function add(int $start, int $end, $text); // add one line or several

    public function remove(int $from, int $till); // delete text from subtitles

    public function shiftTime(int $seconds, ?float $from = 0, ?float $till = null); // add or subtract some amount of seconds from all times

    public function shiftTimeGradually(int $secondsToShift, ?float $from = 0, ?float $till = null);

    public function getInternalFormat();

    public function setInternalFormat(array $internalFormat);
}
