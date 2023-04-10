<?php

declare(strict_types=1);

namespace Circlical\Subtitles\Converters;

use Carbon\CarbonInterval;
use Circlical\Subtitles\Exception\InvalidSubtitleContentsException;
use Circlical\Subtitles\Exception\InvalidTimeFormatException;
use Circlical\Subtitles\Providers\ConstantsInterface;
use Circlical\Subtitles\Providers\ConverterInterface;
use Closure;

use function array_map;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strpos;
use function substr;
use function trim;

class VttConverter implements ConverterInterface
{
    public function parseSubtitles(string $fileContent): array
    {
        $internalFormat = [];
        $fileContent = preg_replace('/\n\n+/', "\n\n", $fileContent);
        $blocks = explode("\n\n", trim($fileContent));

        foreach ($blocks as $block) {
            if (preg_match('/^WEBVTT.{0,}/', $block, $matches)) {
                continue;
            }

            $lines = explode("\n", $block); // separate all block lines

            if (strpos($lines[0], '-->') === false) { // first line not containing '-->', must be cue id
                unset($lines[0]); // not supporting cue id
                $lines = array_values($lines);
            }

            if (empty($lines[0]) || strpos($lines[0], '-->') === false) {
                throw new InvalidSubtitleContentsException();
            }

            $times = explode(' --> ', $lines[0]);

            $linesArray = array_map(static::fixLine(), array_slice($lines, 1)); // get all the remaining lines from block (if multiple lines of text)
            if (count($linesArray) === 0) {
                continue;
            }

            $internalFormat[] = [
                'start' => $this->toInternalTimeFormat($times[0]),
                'end' => $this->toInternalTimeFormat($times[1]),
                'lines' => $linesArray,
            ];
        }

        return $internalFormat;
    }

    public function toSubtitles(array $internalFormat): string
    {
        $fileContent = "WEBVTT\n\n";

        foreach ($internalFormat as $k => $block) {
            $start = $this->toSubtitleTimeFormat($block['start']);
            $end = $this->toSubtitleTimeFormat($block['end']);
            $lines = implode("\n", $block['lines']);

            $fileContent .= $start . ' --> ' . $end . "\n";
            $fileContent .= $lines . "\n";
            $fileContent .= "\n";
        }

        return trim($fileContent);
    }

    protected static function fixLine(): Closure
    {
        return function ($line) {
            if (substr($line, 0, 3) === '<v ') {
                $line = substr($line, 3);
                $line = str_replace('>', ' ', $line);
            }

            return $line;
        };
    }

    /**
     * 00:00:00.500 --> xx.yyy
     */
    public function toInternalTimeFormat(string $subtitleFormat): float
    {
        if (preg_match('/^(?<hours>\\d{2,5}):(?<minutes>\\d{2}):(?<seconds>\\d{2}).(?<fraction>\\d{3})$/us', $subtitleFormat, $matches) === false) {
            throw new InvalidTimeFormatException($subtitleFormat);
        }

        return (int) $matches['hours'] * ConstantsInterface::HOURS_SECONDS
            + (int) $matches['minutes'] * ConstantsInterface::MINUTES_SECONDS
            + (int) $matches['seconds']
            + (float) $matches['fraction'] / 1000;
    }

    /**
     * xx.yyy -> 00:00:00.500
     */
    public function toSubtitleTimeFormat(float $internalFormat): string
    {
        $interval = CarbonInterval::createFromFormat("s.u", sprintf("%.3F", $internalFormat))->cascade();

        return sprintf(
            "%02d:%02d:%02d.%03d",
            $interval->hours,
            $interval->minutes,
            $interval->seconds,
            $interval->milliseconds
        );
    }
}
