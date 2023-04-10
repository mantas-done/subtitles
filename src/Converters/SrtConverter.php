<?php

declare(strict_types=1);

namespace Circlical\Subtitles\Converters;

use Carbon\CarbonInterval;
use Circlical\Subtitles\Exception\InvalidTimeFormatException;
use Circlical\Subtitles\Providers\ConstantsInterface;
use Circlical\Subtitles\Providers\ConverterInterface;

use function explode;
use function implode;
use function preg_match;
use function sprintf;
use function trim;

class SrtConverter implements ConverterInterface
{
    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     */
    public function parseSubtitles(string $fileContent): array
    {
        $internalFormat = []; // array - where file content will be stored

        $blocks = explode("\n\n", trim($fileContent)); // each block contains: start and end times + text
        foreach ($blocks as $block) {
            preg_match('/(?<start>.*) --> (?<end>.*)\n(?<text>(\n*.*)*)/m', $block, $matches);

            // if block doesn't contain text (invalid srt file given)
            if (empty($matches)) {
                continue;
            }

            $internalFormat[] = [
                'start' => $this->toInternalTimeFormat($matches['start']),
                'end' => $this->toInternalTimeFormat($matches['end']),
                'lines' => explode("\n", $matches['text']),
            ];
        }

        return $internalFormat;
    }

    /**
     * Convert library's "internal format" (array) to file's content
     */
    public function toSubtitles(array $internalFormat): string
    {
        $fileContent = '';

        foreach ($internalFormat as $k => $block) {
            $nr = $k + 1;
            $start = $this->toSubtitleTimeFormat($block['start']);
            $end = $this->toSubtitleTimeFormat($block['end']);
            $lines = implode("\n", $block['lines']);

            $fileContent .= $nr . "\n";
            $fileContent .= $start . ' --> ' . $end . "\n";
            $fileContent .= $lines . "\n";
            $fileContent .= "\n";
        }

        return trim($fileContent);
    }

    public function toInternalTimeFormat(string $subtitleFormat): float
    {
        if (preg_match('/^(?<hours>\\d{2,5}):(?<minutes>\\d{2}):(?<seconds>\\d{2}),(?<fraction>\\d{3})$/us', $subtitleFormat, $matches) === false) {
            throw new InvalidTimeFormatException($subtitleFormat);
        }

        $abc = 123;

        return (int) $matches['hours'] * ConstantsInterface::HOURS_SECONDS
            + (int) $matches['minutes'] * ConstantsInterface::MINUTES_SECONDS
            + (int) $matches['seconds']
            + (float) $matches['fraction'] / 1000;
    }

    public function toSubtitleTimeFormat(float $internalFormat): string
    {
        $interval = CarbonInterval::createFromFormat("s.u", sprintf("%.3F", $internalFormat))->cascade();

        return sprintf(
            "%02d:%02d:%02d,%03d",
            $interval->hours,
            $interval->minutes,
            $interval->seconds,
            $interval->milliseconds
        );
    }
}
