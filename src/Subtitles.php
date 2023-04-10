<?php

declare(strict_types=1);

namespace Circlical\Subtitles;

use Circlical\Subtitles\Providers\SubtitleInterface;

use function array_values;
use function is_array;
use function strtolower;
use function trim;
use function usort;

class Subtitles implements SubtitleInterface
{
    protected string $input;
    protected string $inputFormat;
    protected array $internalFormat; // data in internal format (when file is converted)
    protected string $output;

    /** @param string|array|mixed $text */
    public function add(float $start, float $end, $text): SubtitleInterface
    {
        $this->internalFormat[] = [
            'start' => $start,
            'end' => $end,
            'lines' => is_array($text) ? $text : [$text],
        ];

        $this->sortInternalFormat();

        return $this;
    }

    public function remove(int $from, int $till): SubtitleInterface
    {
        foreach ($this->internalFormat as $k => $block) {
            if ($this->shouldBlockBeRemoved($block, $from, $till)) {
                unset($this->internalFormat[$k]);
            }
        }

        $this->internalFormat = array_values($this->internalFormat); // reorder keys

        return $this;
    }

    public function shiftTime(int $seconds, ?float $from = 0, ?float $till = null): SubtitleInterface
    {
        foreach ($this->internalFormat as &$block) {
            if (!Helpers::shouldBlockTimeBeShifted($from, $till, $block['start'], $block['end'])) {
                continue;
            }

            $block['start'] += $seconds;
            $block['end'] += $seconds;
        }
        unset($block);

        $this->sortInternalFormat();

        return $this;
    }

    public function shiftTimeGradually(int $seconds, ?float $from = 0, ?float $till = null): SubtitleInterface
    {
        if ($till === null) {
            $till = $this->maxTime();
        }

        foreach ($this->internalFormat as &$block) {
            $block = Helpers::shiftBlockTime($block, $seconds, $from, $till);
        }
        unset($block);

        $this->sortInternalFormat();

        return $this;
    }

    public function content(string $format): string
    {
        $format = strtolower(trim($format, '.'));
        $converter = Helpers::getConverter($format);

        return $converter->toSubtitles($this->internalFormat);
    }

    /** for testing only */
    public function getInternalFormat(): array
    {
        return $this->internalFormat;
    }

    public function setInternalFormat(array $internalFormat): Subtitles
    {
        $this->internalFormat = $internalFormat;

        return $this;
    }

    /** private */
    protected function sortInternalFormat(): void
    {
        usort($this->internalFormat, function ($item1, $item2) {
            if ($item2['start'] === $item1['start']) {
                return 0;
            } elseif ($item2['start'] < $item1['start']) {
                return 1;
            } else {
                return -1;
            }
        });
    }

    protected function maxTime(): float
    {
        $maxTime = 0;
        foreach ($this->internalFormat as $block) {
            if ($maxTime < $block['end']) {
                $maxTime = $block['end'];
            }
        }

        return $maxTime;
    }

    protected function shouldBlockBeRemoved(array $block, float $from, float $till): bool
    {
        return ($from < $block['start'] && $block['start'] < $till) || ($from < $block['end'] && $block['end'] < $till);
    }

    public static function load(string $subtitleText, string $extension): SubtitleInterface
    {
        $converter = new static();
        $converter->input = Helpers::normalizeNewLines(Helpers::removeUtf8Bom($subtitleText));
        $converter->inputFormat = $extension;

        $inputConverter = Helpers::getConverter($extension);
        $converter->internalFormat = $inputConverter->parseSubtitles($converter->input);

        return $converter;
    }
}
