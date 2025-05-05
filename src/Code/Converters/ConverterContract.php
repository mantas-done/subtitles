<?php

namespace Done\Subtitles\Code\Converters;

interface ConverterContract
{
    /**
     * Check whether
     *
     * @param string $file_content
     * @return bool
     */
    public function canParseFileContent(string $file_content, string $original_file_content): bool;

    /**
     * Converts file content (.srt, .stl... file content) to library's "internal format"
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array;

    /**
     * Convert library's "internal format" to file's content
     *
     * @param array $internal_format    Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internal_format, array $output_settings): string;

}