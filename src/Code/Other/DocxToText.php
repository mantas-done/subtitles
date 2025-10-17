<?php

namespace Done\Subtitles\Code\Other;

use Done\Subtitles\Code\Exceptions\UserException;

class DocxToText
{
    /** @throws UserException */
    public static function text(string $original_file_content): string
    {
        $tmp_path = tempnam(sys_get_temp_dir(), 'prefix_');
        file_put_contents($tmp_path, $original_file_content);

        $zip = new \ZipArchive();
        $opened = $zip->open($tmp_path, \ZipArchive::RDONLY); // zip archive can only open real file
        if ($opened !== true) {
            unlink($tmp_path);
            throw new \RuntimeException("Can't open zip");
        }

        $content = '';
        $content .= $zip->getFromName('word/document.xml') ?: '';
        $content .= $zip->getFromName('word/document2.xml') ?: '';

        $content = str_replace('<w:tab/>', "    ", $content); // tab
        $content = str_replace('<w:pStyle w:val="ListParagraph"/>', '1. ', $content); // numbering but not correct, jus for word count
        $content = preg_replace('/<w:drawing>.*<\/w:drawing>/Um', '', $content) ?? throw new \RuntimeException();
        $content = preg_replace('/<w:instrText.*<\/w:instrText>/Um', '', $content) ?? throw new \RuntimeException();
        $content = str_replace('</w:r></w:p>', "\r\n", $content);
        $striped_content = strip_tags($content);
        $striped_content = html_entity_decode($striped_content, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');

        $zip->close();
        unlink($tmp_path);

        if (trim($striped_content) === '') {
            throw new UserException('No text found in .docx file');
        }

        return $striped_content;
    }
}