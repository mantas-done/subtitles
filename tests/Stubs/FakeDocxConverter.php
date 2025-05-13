<?php

namespace Tests\Stubs;

use Done\Subtitles\Code\Converters\ConverterContract;
use Done\Subtitles\Code\Helpers;

class FakeDocxConverter implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return Helpers::strContains($file_content, 'fake_docx');
    }

    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        return [[
            'start' => 22,
            'end' => 33,
            'lines' => [$original_file_content],
        ]];
    }

    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        return $internal_format[0]['lines'][0];
    }
}