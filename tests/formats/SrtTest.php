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
    
        public function testStringToInternalFormatWithMissingText()
    {
        $content = <<< TEXT
0
00:00:00,010 --> 00:00:07,777


1
00:00:03,371 --> 00:00:07,406
For 13 years, the<i> cassini</i> spacecraft explored

2
00:00:07,408 --> 00:00:12,845
astounding worlds ... saturn and its moons.

3
00:00:38,865 --> 00:00:40,822
Lorem Ispum.
Lorem ipsum dolor sit amet
TEXT;

        $actual_internal_format = Subtitles::load($content, $this->format)->getInternalFormat();

        $expected = (new Subtitles())
            ->add(00.010, 7.777, '')
            ->add(3.371, 7.406, ['For 13 years, the<i> cassini</i> spacecraft explored'])
            ->add(7.408, 12.845, ['astounding worlds ... saturn and its moons.'])
            ->add(38.865, 40.822, ['Lorem Ispum.', 'Lorem ipsum dolor sit amet'])
            ->getInternalFormat();
        $actual = $actual_internal_format;

        $this->assertInternalFormatsEqual($expected, $actual);
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
