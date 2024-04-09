<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Other\DocxToText;
use Done\Subtitles\Subtitles;

class DocxReader implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return strpos($file_content, 'PK') === 0 && strpos($file_content, '[Content_Types].xml') !== false;
    }

    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
        $text = DocxToText::text($original_file_content);
        return Subtitles::loadFromString($text)->getInternalFormat();
    }

    public function internalFormatToFileContent(array $internal_format, array $options)
    {
        throw new \Exception('not implemented');
    }


}