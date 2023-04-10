<?php

use Circlical\Subtitles\Exception\InvalidSubtitleContentsException;
use Circlical\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;

class VttTest extends TestCase
{
    use AdditionalAssertions;

    public function testConvertFromVttToSrt()
    {
        $vttContent = file_get_contents('./tests/files/vtt.vtt');
        $srtContent = file_get_contents('./tests/files/srt.srt');

        $actual = (new Subtitles())->load($vttContent, 'vtt')->content('srt');
        $this->assertEquals($actual, $srtContent);
    }

    public function testConvertFromSrtToVtt()
    {
        $vttContent = file_get_contents('./tests/files/vtt.vtt');
        $srtContent = file_get_contents('./tests/files/srt.srt');

        $actual = (new Subtitles())->load($srtContent, 'srt')->content('vtt');
        $this->assertEquals($vttContent, $actual);
    }

    public function testFileToInternalFormat()
    {
        $expected = [
            [
                'start' => 9.0,
                'end' => 11.0,
                'lines' => ['Roger Bingham We are in New York City'],
            ],
        ];

        $internalformat = Subtitles::load(file_get_contents('./tests/files/vtt_with_name.vtt'), 'vtt')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $internalformat);
    }

    public function timeTest()
    {
        $converter = new VttConverter();
        $internalFormat = $converter->toInternalTimeFormat('00:00:11.000');
        $this->assertEquals(11.0, $internalFormat);
    }

    public function testConvertToInternalFormatWhenFileContainsNumbers() // numbers are optional in webvtt format
    {
        $inputVttContent = <<<TEXT
WEBVTT

1
00:00:09.000 --> 00:00:11.000
Roger Bingham We are in New York City
TEXT;

        $expectedVttContent = <<<TEXT
WEBVTT

00:00:09.000 --> 00:00:11.000
Roger Bingham We are in New York City
TEXT;

        $generatedVttContent = (new Subtitles())->load($inputVttContent, 'vtt')->content('vtt');
        $this->assertEquals($expectedVttContent, $generatedVttContent);
    }

    public function testParsesFileWithMissingText()
    {
        $vttContent = file_get_contents('./tests/files/vtt_with_missing_text.vtt');
        $actual = (new Subtitles())->load($vttContent, 'vtt')->getInternalFormat();
        $expected = [
            [
                'start' => 0,
                'end' => 1,
                'lines' => [
                    'one',
                ],
                [
                    'start' => 2,
                    'end' => 3,
                    'lines' => [
                        'three',
                    ],
                ],
            ],
        ];
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testFileContainingMultipleNewLinesBetweenBlocks()
    {
        $given = <<<TEXT
WEBVTT

00:00:00.000 --> 00:00:01.000
text1





00:00:01.000 --> 00:00:02.000
text2
TEXT;
        $actual = (new Subtitles())->load($given, 'vtt')->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0, 1, 'text1')
            ->add(1, 2, 'text2')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testThrowsAnExceptionWhenFilesContainGarbage()
    {
        $this->expectException(InvalidSubtitleContentsException::class);
        $vttContent = file_get_contents('./tests/files/garbage.vtt');
        (new Subtitles())->load($vttContent, 'vtt')->getInternalFormat();
    }
}