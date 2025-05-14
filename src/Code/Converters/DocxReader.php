<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Other\DocxToText;
use Done\Subtitles\Subtitles;

class DocxReader implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }
        $mime_type = finfo_buffer($finfo, $original_file_content);
        if ($mime_type === false) {
            return false;
        }
        finfo_close($finfo);
        return $mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $text = DocxToText::text($original_file_content);
        return (new Subtitles())->loadFromString($text)->getInternalFormat();
    }

    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        throw new \Exception('not implemented');
    }


}