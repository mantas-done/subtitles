<?php

declare(strict_types=1);

namespace Circlical\Subtitles\Providers;

interface ConverterInterface
{
    /**
     * Converts file content (.srt, .stl... file content) to library's "internal format"
     */
    public function parseSubtitles(string $fileContent): array;

    /**
     * Convert library's "internal format" to file's content
     */
    public function toSubtitles(array $internalFormat): string;

    public function toInternalTimeFormat(string $subtitleFormat): float;

    public function toSubtitleTimeFormat(float $internalFormat): string;
}
