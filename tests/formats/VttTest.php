<?php

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;

class VttSubtitle extends TestCase {

    use AdditionalAssertions;

    public function testFileToInternalFormat()
    {
        $vtt_path = './tests/files/vtt.vtt';
        $expected_internal_format = [[
            'start' => 9,
            'end' => 11,
            'lines' => ['Roger Bingham We are in New York City'],
        ]];

        $actual_internal_format = Subtitles::load($vtt_path)->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }



    public function testConvertToFile()
    {
        $expected_vtt_file_content = <<< TEXT
WEBVTT

00:09.000 --> 00:11.000
Roger Bingham We are in New York City
TEXT;
        $expected_vtt_file_content = str_replace("\r", '', $expected_vtt_file_content);

        $actual_vtt_file_content = (new Subtitles())->add(9, 11, 'Roger Bingham We are in New York City')->content('vtt');

        $this->assertEquals($expected_vtt_file_content, $actual_vtt_file_content);
    }

    // @TODO test time above 1 hour

}