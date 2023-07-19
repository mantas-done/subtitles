<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SrtConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class SrtTest extends TestCase {

    use AdditionalAssertionsTrait;

    protected $format = 'srt';

    public function testRecognizesSrt()
    {
        $content = file_get_contents('./tests/files/srt.srt');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === SrtConverter::class);
    }

    public function testConvertingFileFromSrtToSrtDoesNotChangeItContent()
    {
        $srt_path = './tests/files/srt.srt';
        $temporary_srt_path = './tests/files/tmp/srt.srt';

        @unlink($temporary_srt_path);

        Subtitles::convert($srt_path, $temporary_srt_path);
        $this->assertFileEqualsIgnoringLineEndings($srt_path, $temporary_srt_path);

        unlink($temporary_srt_path);
    }

    public function testFileToInternalFormat()
    {
        $actual_internal_format = Subtitles::loadFromString(self::fileContent(), $this->format)->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $actual_internal_format);
    }

    public function testConvertToFile()
    {
        $actual_file_content = self::generatedSubtitles()->content($this->format);

        $this->assertStringEqualsStringIgnoringLineEndings(self::fileContent(), $actual_file_content);
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

        $actual_format = Subtitles::loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(3, 4, ['Very good, Lieutenant.'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);

    }

    public function testParsesClientFile()
    {
        $content = <<< TEXT
1
00:00:01,000-->00:00:02,000
1

TEXT;

        $actual_format = Subtitles::loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, ['1'])
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

    public function testTimeFormats()
    {
        $content = <<< TEXT
1
00:00:01,100-->00:00:02,200
one

2
00:00:02.200-->00:00:03.300
two

3
00:00:04-->00:00:05
three

4
0:00:05-->0:00:06
four

5
0:00:06,5-->0:00:07,11
five
TEXT;
        $actual_format = Subtitles::loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1.1, 2.2, ['one'])
            ->add(2.2, 3.3, ['two'])
            ->add(4, 5, ['three'])
            ->add(5, 6, ['four'])
            ->add(6.5, 7.11, ['five'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

    public function testParseFileWithTags()
    {
        $content = <<< TEXT
1
00:00:01,000-->00:00:02,000
<font color=”#rrggbb”>one</font>

2
00:00:02.000-->00:00:03.000
<i>two</i>
TEXT;
        $actual_format = Subtitles::loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, ['one'])
            ->add(2, 3, ['two'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

    public function testParseFileSubtitleNumbersWithLeadingZeros()
    {
        $content = <<< TEXT
001
00:00:01,000-->00:00:02,000
one

002
00:00:02.000-->00:00:03.000
two
TEXT;
        $actual_format = Subtitles::loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, ['one'])
            ->add(2, 3, ['two'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

}