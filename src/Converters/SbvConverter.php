<?php

declare(strict_types=1);

namespace Circlical\Subtitles\Converters;

use Carbon\CarbonInterval;
use Circlical\Subtitles\Exception\InvalidSubtitleContentsException;
use Circlical\Subtitles\Exception\InvalidTimeFormatException;
use Circlical\Subtitles\Providers\ConstantsInterface;
use Circlical\Subtitles\Providers\ConverterInterface;

use function array_slice;
use function explode;
use function implode;
use function preg_match;
use function sprintf;
use function trim;

class SbvConverter implements ConverterInterface
{
    public function parseSubtitles(string $fileContent): array
    {
        $internalFormat = []; // array - where file content will be stored

        $blocks = explode("\n\n", trim($fileContent)); // each block contains: start and end times + text
        foreach ($blocks as $block) {
            $lines = explode("\n", $block); // separate all block lines

            if (empty($lines[0]) || empty($lines[1])) {
                throw new InvalidSubtitleContentsException();
            }

            $times = explode(',', $lines[0]); // one the second line there is start and end times

            $internalFormat[] = [
                'start' => $this->toInternalTimeFormat($times[0]),
                'end' => $this->toInternalTimeFormat($times[1]),
                'lines' => array_slice($lines, 1), // get all the remaining lines from block (if multiple lines of text)
            ];
        }

        return $internalFormat;
    }

    public function toSubtitles(array $internalFormat): string
    {
        $fileContent = '';

        foreach ($internalFormat as $k => $block) {
            $start = $this->toSubtitleTimeFormat($block['start']);
            $end = $this->toSubtitleTimeFormat($block['end']);
            $lines = implode("\n", $block['lines']);

            $fileContent .= $start . ',' . $end . "\n";
            $fileContent .= $lines . "\n";
            $fileContent .= "\n";
        }

        $fileContent = trim($fileContent);

        return $fileContent;
    }

    /**
     * 00:00:00.500 --> xx.yyy
     */
    public function toInternalTimeFormat(string $subtitleFormat): float
    {
        if (preg_match('/^(?<hours>\\d{1,5}):(?<minutes>\\d{2}):(?<seconds>\\d{2}).(?<fraction>\\d{3})$/us', $subtitleFormat, $matches) === false) {
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
            "%01d:%02d:%02d.%03d",
            $interval->hours,
            $interval->minutes,
            $interval->seconds,
            $interval->milliseconds
        );
    }
}
