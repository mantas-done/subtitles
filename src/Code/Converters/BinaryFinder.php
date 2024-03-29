<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;

class BinaryFinder implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        if (Helpers::strContains($file_content, "\x00")) {
            throw new UserException('This file is binary file and it is probably not a caption file.');
        }

        return false;
    }

    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
        // no code needed
    }

    public function internalFormatToFileContent(array $internal_format , array $options)
    {
        // no code needed
    }
}
