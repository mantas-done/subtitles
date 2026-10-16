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

        $parts = ['word/document.xml', 'word/document2.xml'];
        try {
            self::assertSafeUncompressedSize($zip, $parts);
        } catch (UserException $e) {
            $zip->close();
            unlink($tmp_path);
            throw $e;
        }

        $content = '';
        foreach ($parts as $part) {
            $content .= $zip->getFromName($part) ?: ''; // reads at most the size declared in the zip index, which was checked above
        }

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

    private const MAX_COMPRESSION_RATIO = 100;
    private const RATIO_CHECK_MIN_SIZE = 1024 * 1024;
    private const MAX_TOTAL_UNCOMPRESSED_SIZE = 100 * 1024 * 1024;

    /** @throws UserException */
    private static function assertSafeUncompressedSize(\ZipArchive $zip, array $part_names): void
    {
        $total_size = 0;
        foreach ($part_names as $part_name) {
            $stat = $zip->statName($part_name);
            if ($stat === false) {
                continue;
            }

            $too_compressed = $stat['size'] > self::RATIO_CHECK_MIN_SIZE && $stat['size'] > self::MAX_COMPRESSION_RATIO * $stat['comp_size'];
            $total_size += $stat['size'];
            if ($too_compressed || $total_size > self::MAX_TOTAL_UNCOMPRESSED_SIZE) {
                throw new UserException('.docx file is too large when uncompressed');
            }
        }
    }
}