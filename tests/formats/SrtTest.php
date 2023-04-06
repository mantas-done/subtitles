<?php

use Circlical\Subtitles\Providers\SubtitleInterface;
use Circlical\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;

class SrtTest extends TestCase
{
    use AdditionalAssertions;

    protected string $format = 'srt';

    public function testConvertingFileFromSrtToSrtDoesNotChangeItContent()
    {
        $srtContent = file_get_contents('./tests/files/srt.srt');
        $this->assertEquals(Subtitles::load($srtContent, 'srt')->content('srt'), $srtContent);
    }

    public function testFileToInternalFormat()
    {
        $generatedFormat = Subtitles::load(self::fileContent(), $this->format)->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $generatedFormat);
    }

    public function testConvertToFile()
    {
        $fileContent = self::generatedSubtitles()->content($this->format);
        $this->assertEquals(self::fileContent(), $fileContent);
    }

    public function testRemovesEmptyLines()
    {
        $content = <<<TEXT
1
00:00:01,000 --> 00:00:02,000


2
00:00:03,000 --> 00:00:04,000
Very good, Lieutenant.
TEXT;

        $generatedFormat = Subtitles::load($content, 'srt')->getInternalFormat();
        $expectedFormat = (new Subtitles())
            ->add(3, 4, ['Very good, Lieutenant.'])
            ->getInternalFormat();
        $this->assertEquals($expectedFormat, $generatedFormat);
    }

    private static function fileContent(): string
    {
        return <<<TEXT
1
00:02:17,440 --> 00:02:20,375
Senator, we're making
our final approach into Coruscant.

2
01:02:20,476 --> 01:02:22,501
Very good, Lieutenant.
TEXT;
    }

    private static function generatedSubtitles(): SubtitleInterface
    {
        return (new Subtitles())
            ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.476, 3742.501, ['Very good, Lieutenant.']);
    }

}