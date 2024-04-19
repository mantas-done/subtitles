<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Other\DocxToText;
use Done\Subtitles\Subtitles;

class DocxReader implements ConverterContract
{
    public function canParseFileContent($file_content, $original_file_content)
    {
        if (strpos($original_file_content, 'PK') === 0 && strpos($original_file_content, '[Content_Types].xml') !== false) {
            $tmp_file = tempnam(sys_get_temp_dir(), 'prefix_');
            file_put_contents($tmp_file, $original_file_content);

            $zip = new \ZipArchive();
            $opened = $zip->open($tmp_file, \ZipArchive::RDONLY); // zip archive can only open real file
            if ($opened === true) {
                $zip->close();
            }
            unlink($tmp_file);

            if ($opened === true) {
                return true;
            }
        }

        return false;
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