<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Other\DocxToText;
use Done\Subtitles\Subtitles;

class DocxReader implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
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

    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
    {
        $text = DocxToText::text($original_file_content);
        return (new Subtitles())->loadFromString($text)->getInternalFormat();
    }

    /** @throws UserException */
    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        throw new UserException('DOCX writer is not implemented yet');
    }


}