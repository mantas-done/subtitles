<?php

declare(strict_types=1);

namespace Circlical\Subtitles\Providers;

interface SubtitleInterface
{
    public static function load(string $subtitleText, string $extension): SubtitleInterface;

    public function content(string $format); // output file content (instead of saving to file)

    /** @param string|array|mixed $text */
    public function add(float $start, float $end, string $text); // add one line or several

    public function remove(int $from, int $till); // delete text from subtitles

    public function shiftTime(int $seconds, ?float $from = 0, ?float $till = null); // add or subtract some amount of seconds from all times

    public function shiftTimeGradually(int $secondsToShift, ?float $from = 0, ?float $till = null);

    public function getInternalFormat(): array;
}
