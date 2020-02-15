<?php

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;

class SrtTest extends TestCase {

    use AdditionalAssertions;

    protected $format = 'srt';

    public function testConvertingFileFromSrtToSrtDoesNotChangeItContent()
    {
        $srt_path = './tests/files/srt.srt';
        $temporary_srt_path = './tests/files/tmp/srt.srt';

        @unlink($temporary_srt_path);

        Subtitles::convert($srt_path, $temporary_srt_path);
        $this->assertFileEquals($srt_path, $temporary_srt_path);

        unlink($temporary_srt_path);
    }

    public function testFileToInternalFormat()
    {
        $actual_internal_format = Subtitles::load(self::fileContent(), $this->format)->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $actual_internal_format);
    }

    public function testConvertToFile()
    {
        $actual_file_content = self::generatedSubtitles()->content($this->format);

        $this->assertEquals(self::fileContent(), $actual_file_content);
    }

    public function testRemovesEmptyLines()
    {
        $content = <<< TEXT
1
00:00:01,000 --> 00:00:02,000


2
00:00:03,000 --> 00:00:04,000
Very good, Lieutenant.
TEXT;

        $actual_format = Subtitles::load($content, 'srt')->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(3, 4, ['Very good, Lieutenant.'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);

    }

    // ---------------------------------- private ----------------------------------------------------------------------

    private static function fileContent()
    {
        $content = <<< TEXT
1
00:02:17,440 --> 00:02:20,375
Senator, we're making
our final approach into Coruscant.

2
01:02:20,476 --> 01:02:22,501
Very good, Lieutenant.
TEXT;

        return $content;
    }

    private static function generatedSubtitles()
    {
        return (new Subtitles())
            ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.476, 3742.501, ['Very good, Lieutenant.']);
    }

}