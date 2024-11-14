<?php

namespace Done\Subtitles\Code\Converters;


class CapConverter implements ConverterContract
{
    public function canParseFileContent($file_content, $original_file_content)
    {
        return true;
    }

    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
        $parts = explode("\x10", $original_file_content);




        print_r($parts);
        exit;
    }

    public function internalFormatToFileContent(array $internal_format , array $options)
    {
        throw new \Exception("Not implemented yet");
    }
}
