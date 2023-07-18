<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\VttConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class VttTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesSrt()
    {
        $content = file_get_contents('./tests/files/vtt.vtt');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === VttConverter::class);
    }

    public function testConvertFromVttToSrt()
    {
        $vtt_path = './tests/files/vtt.vtt';
        $srt_path = './tests/files/srt.srt';

        $expected = (new Subtitles())->loadFromFile($vtt_path)->content('srt');
        $actual = file_get_contents($srt_path);

        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testConvertFromSrtToVtt()
    {
        $srt_path = './tests/files/srt.srt';
        $vtt_path = './tests/files/vtt.vtt';

        $expected = file_get_contents($vtt_path);
        $actual = (new Subtitles())->loadFromFile($srt_path)->content('vtt');

        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testFileToInternalFormat()
    {
        $vtt_path = './tests/files/vtt_with_name.vtt';
        $expected_internal_format = [[
            'start' => 9,
            'end' => 11,
            'lines' => ['Roger Bingham: We are in New York City'],
        ]];

        $actual_internal_format = Subtitles::loadFromFile($vtt_path)->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testConvertToInternalFormatWhenFileContainsNumbers() // numbers are optional in webvtt format
    {
        $input_vtt_file_content = <<< TEXT
WEBVTT

1
00:00:09.000 --> 00:00:11.000
Roger Bingham: We are in New York City
TEXT;
        $expected_vtt_file_content = <<< TEXT
WEBVTT

00:00:09.000 --> 00:00:11.000
Roger Bingham: We are in New York City
TEXT;

        $actual_vtt_file_content = (new Subtitles())->loadFromString($input_vtt_file_content, 'vtt')->content('vtt');

        $this->assertStringEqualsStringIgnoringLineEndings($expected_vtt_file_content, $actual_vtt_file_content);
    }

    public function testParsesFileWithMissingText()
    {
        $vtt_path = './tests/files/vtt_with_missing_text.vtt';
        $actual = (new Subtitles())->loadFromFile($vtt_path)->getInternalFormat();
        $expected = [
            [
                'start' => 0,
                'end' => 1,
                'lines' => [
                    'one',
                ],
            ], [
                'start' => 2,
                'end' => 3,
                'lines' => [
                    'three',
                ],
            ]
        ];
        $this->assertInternalFormatsEqual($expected, $actual);
    }

        public function testFileContainingMultipleNewLinesBetweenBlocks()
    {
        $given = <<< TEXT
WEBVTT

00:00:00.000 --> 00:00:01.000
text1





00:00:01.000 --> 00:00:02.000
text2
TEXT;
        $actual = (new Subtitles())->loadFromString($given, 'vtt')->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0, 1, 'text1')
            ->add(1, 2, 'text2')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParsesFileWithStyles()
    {
        $given = file_get_contents('./tests/files/vtt_with_styles.vtt');
        $actual = (new Subtitles())->loadFromString($given, 'vtt')->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0, 10, 'Hello world.')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParsesFileWithHtml()
    {
        $given = file_get_contents('./tests/files/vtt_with_html.vtt');
        $actual = (new Subtitles())->loadFromString($given, 'vtt')->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0.0, 10.0, 'Sur les playground, ici à Montpellier')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParsesFileWithoutHoursInTimestamp()
    {
        $given = file_get_contents('./tests/files/vtt_without_hours_in_timestamp.vtt');
        $actual = (new Subtitles())->loadFromString($given, 'vtt')->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0.0, 10.0, "I've spent nearly two decades")
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParsesFileWithMultipleNewLines()
    {
        $given = file_get_contents('./tests/files/vtt_with_multiple_new_lines.vtt');
        $actual = (new Subtitles())->loadFromString($given, 'vtt')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0.0, 1.0, ['one', 'two'])
            ->add(2.0, 3.0, 'three')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParseFileWithMetadata()
    {
        $given = <<< TEXT
WEBVTT
X-TIMESTAMP-MAP=LOCAL:00:00:00.000,MPEGTS:0

00:00:00.000 --> 00:00:01.000
text1
TEXT;
        $actual = (new Subtitles())->loadFromString($given, 'vtt')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, 'text1')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParseFileWithSpacesBetweenTimestamps()
    {
        $given = <<< TEXT
WEBVTT

     00:00:00.100    -->    00:00:01.100     
text1
TEXT;
        $actual = (new Subtitles())->loadFromString($given, 'vtt')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0.1, 1.1, 'text1')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }
}