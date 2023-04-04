<?php

declare(strict_types=1);

namespace Done\Subtitles;

use Done\Subtitles\Providers\ConverterInterface;
use Done\Subtitles\Providers\SubtitleInterface;
use Exception;

use function array_values;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function strtolower;
use function trim;
use function usort;

class Subtitles implements SubtitleInterface
{
    protected string $input;
    protected string $inputFormat;

    protected array $internalFormat; // data in internal format (when file is converted)

    protected ConverterInterface $converter;
    protected string $output;

    public static function convert(string $fromFilePath, string $toFilePath): Subtitles
    {
        static::load($fromFilePath)->save($toFilePath);
    }

    public static function load(string $fileNameOrFileContent, ?string $extension = null): SubtitleInterface
    {
        if (file_exists($fileNameOrFileContent)) {
            return static::loadFile($fileNameOrFileContent);
        }

        return static::loadString($fileNameOrFileContent, $extension);
    }

    public function save(string $path): SubtitleInterface
    {
        $fileExtension = Helpers::fileExtension($path);
        $content = $this->content($fileExtension);

        file_put_contents($path, $content);

        return $this;
    }

    /** @param string|array|mixed $text */
    public function add(int $start, int $end, $text): SubtitleInterface
    {
        // @TODO validation
        // @TODO check subtitles to not overlap
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
        return $converter->internalFormatToFileContent($this->internalFormat);
    }

    /** for testing only */
    public function getInternalFormat(): array
    {
        return $this->internalFormat;
    }

    /** for testing only */
    public function setInternalFormat(array $internalFormat): SubtitleInterface
    {
        $this->internalFormat = $internalFormat;

        return $this;
    }

    /**
     * @deprecated  Use shiftTime() instead
     */
    public function time(int $seconds, ?float $from = null, ?float $till = null): SubtitleInterface
    {
        return $this->shiftTime($seconds, $from, $till);
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

    protected function maxTime(): int
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

    public static function loadFile(string $path, ?string $extension = null): SubtitleInterface
    {
        if (!file_exists($path)) {
            throw new Exception("file doesn't exist: " . $path);
        }

        $string = file_get_contents($path);
        if (!$extension) {
            $extension = Helpers::fileExtension($path);
        }

        return static::loadString($string, $extension);
    }

    public static function loadString(string $text, string $extension): SubtitleInterface
    {
        $converter = new static();
        $converter->input = Helpers::normalizeNewLines(Helpers::removeUtf8Bom($text));

        $converter->inputFormat = $extension;

        $inputConverter = Helpers::getConverter($extension);
        $converter->internalFormat = $inputConverter->fileContentToInternalFormat($converter->input);

        return $converter;
    }
}

// https://github.com/captioning/captioning has potential, but :(
// https://github.com/snikch/captions-php too small
