<?php

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;

class VttSubtitle extends TestCase {

    use AdditionalAssertions;

    public function testConvertFromVttToSrt()
    {
        $vtt_path = './tests/files/vtt.vtt';
        $srt_path = './tests/files/srt.srt';

        $expected = (new Subtitles())->load($vtt_path)->content('srt');
        $actual = file_get_contents($srt_path);

        $this->assertEquals($expected, $actual);
    }

    public function testConvertFromSrtToVtt()
    {
        $srt_path = './tests/files/srt.srt';
        $vtt_path = './tests/files/vtt.vtt';

        $expected = file_get_contents($vtt_path);
        $actual = (new Subtitles())->load($srt_path)->content('vtt');

        $this->assertEquals($expected, $actual);
    }

    public function testFileToInternalFormat()
    {
        $vtt_path = './tests/files/vtt_with_name.vtt';
        $expected_internal_format = [[
            'start' => 9,
            'end' => 11,
            'lines' => ['Roger Bingham We are in New York City'],
        ]];

        $actual_internal_format = Subtitles::load($vtt_path)->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testConvertToInternalFormatWhenFileContainsNumbers() // numbers are optional in webvtt format
    {
        $input_vtt_file_content = <<< TEXT
WEBVTT

1
00:00:09.000 --> 00:00:11.000
Roger Bingham We are in New York City
TEXT;
        $expected_vtt_file_content = <<< TEXT
WEBVTT

00:00:09.000 --> 00:00:11.000
Roger Bingham We are in New York City
TEXT;

        $actual_vtt_file_content = (new Subtitles())->load($input_vtt_file_content, 'vtt')->content('vtt');

        $this->assertEquals($expected_vtt_file_content, $actual_vtt_file_content);
    }

    public function testParsesFileWithMissingText()
    {
        $vtt_path = './tests/files/vtt_with_missing_text.vtt';
        $actual = (new Subtitles())->load($vtt_path)->getInternalFormat();
        $expected = [
            [
                'start' => 0,
                'end' => 1,
                'lines' => [
                    'one',
            ], [
                'start' => 2,
                'end' => 3,
                'lines' => [
                    'three',
                ],
            ]
        ]];
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
        $actual = (new Subtitles())->load($given, 'vtt')->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0, 1, 'text1')
            ->add(1, 2, 'text2')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }
}