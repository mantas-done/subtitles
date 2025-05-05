<?php

namespace Done\Subtitles\Code\Other;

class DocxToText
{
    private string $tmp_path;

    // text might not be correctly ordered
    public static function text(string $path): string
    {
        $that = new self($path);

        $content = '';
        $content .= $that->getFileContent('word/document.xml');


//        $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content); // original code poster had this
        $content = str_replace('<w:tab/>', "    ", $content); // tab
        $content = str_replace('<w:pStyle w:val="ListParagraph"/>', '1. ', $content); // numbering but not correct, jus for word count
        $content = preg_replace('/<w:drawing>.*<\/w:drawing>/Um', '', $content) or throw new \RuntimeException();
        $content = preg_replace('/<w:instrText.*<\/w:instrText>/Um', '', $content) or throw new \RuntimeException();
        $content = str_replace('</w:r></w:p>', "\r\n", $content);
        $striped_content = strip_tags($content);
        $striped_content = html_entity_decode($striped_content, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');

        return $striped_content;
    }

    private \ZipArchive $zip;

    private function __construct(string $file_content)
    {
        $tmp_file = tempnam(sys_get_temp_dir(), 'prefix_');
        file_put_contents($tmp_file, $file_content);

        $zip = new \ZipArchive();
        $opened = $zip->open($tmp_file, \ZipArchive::RDONLY); // zip archive can only open real file
        if ($opened !== true) {
            unlink($tmp_file);
            throw new \RuntimeException("Can't open zip");
        }
        $this->zip = $zip;
        $this->tmp_path = $tmp_file;
    }

    public function __destruct()
    {
        $this->zip->close();
        unlink($this->tmp_path);
    }

    private function getFileContent(string $internal_path): string
    {
        $content = $this->zip->getFromName($internal_path);
        if ($content === false) {
            throw new \RuntimeException();
        }
        return $content;
    }
}