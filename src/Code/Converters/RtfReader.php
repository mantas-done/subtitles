<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;
use Done\Subtitles\Subtitles;
use Jstewmc\Rtf\Document;

class RtfReader implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return strpos($file_content, '{\rtf1') === 0;
    }

    public function fileContentToInternalFormat($file_content, $original_file_content)
    {

    }

    public function internalFormatToFileContent(array $internal_format, array $options)
    {
        // not implemented
    }


}