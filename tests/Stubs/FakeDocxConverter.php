<?php

namespace Tests\Stubs;

use Done\Subtitles\Code\Converters\ConverterContract;
use Done\Subtitles\Code\Helpers;

class FakeDocxConverter implements ConverterContract
{
    public function canParseFileContent($file_content, $original_file_content)
    {
        return Helpers::strContains($file_content, 'fake_docx');
    }

    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
        return [[
            'start' => 22,
            'end' => 33,
            'lines' => ['fake'],
        ]];
    }

    public function internalFormatToFileContent(array $internal_format, array $options)
    {
        return 'fake docx text';
    }
}