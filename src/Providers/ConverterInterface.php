<?php

declare(strict_types=1);

namespace Done\Subtitles\Providers;

interface ConverterInterface
{
    /**
     * Converts file content (.srt, .stl... file content) to library's "internal format"
     */
    public function fileContentToInternalFormat(string $file_content): array;

    /**
     * Convert library's "internal format" to file's content
     */
    public function internalFormatToFileContent(array $internal_format): string;
}
