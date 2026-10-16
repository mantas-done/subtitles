<?php

namespace Formats;

use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;
use PHPUnit\Framework\TestCase;

class DocxTest extends TestCase
{
    use AdditionalAssertionsTrait;
    public function testParsesDocxFile()
    {
        $content = file_get_contents('./tests/files/docx.docx');
        $actual = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(137.4, 140.4, ["Senator, we're making", 'our final approach into Coruscant.'])
            ->add(3740.5, 3742.5, ['Very good, Lieutenant.'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testDocxError1()
    {
        $actual = (new Subtitles())->loadFromFile('./tests/files/docx_document2.docx')->content('txt');
        $this->assertStringContainsString('That crooked branch', $actual);
    }

    public function testRejectsZipBomb()
    {
        $tmp_path = tempnam(sys_get_temp_dir(), 'docx_bomb_');
        $zip = new \ZipArchive();
        $zip->open($tmp_path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', str_repeat('a', 20 * 1024 * 1024)); // ~20 KB compressed
        $zip->close();
        $content = file_get_contents($tmp_path);
        unlink($tmp_path);

        $memory_before = memory_get_usage();
        memory_reset_peak_usage();
        try {
            (new Subtitles())->loadFromString($content);
            $this->fail('Expected UserException');
        } catch (UserException $e) {
            $this->assertStringContainsString('too large when uncompressed', $e->getMessage());
        }
        $this->assertLessThan(5 * 1024 * 1024, memory_get_peak_usage() - $memory_before);
    }

    public function testCorruptedZip()
    {
        $this->expectExceptionMessage("Can't find suitable converter for the file");

        $content = file_get_contents('./tests/files/corrupted.zip');
        Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
    }
}