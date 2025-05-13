<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SbvConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class SbvTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesSbv()
    {
        $content = <<< TEXT
0:05:40.000,0:05:46.000
Don’t think that you can just ignore them
because they’re not your children or relatives.
TEXT;
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertTrue(get_class($converter) === SbvConverter::class);
    }

    public function testFileToInternalFormat()
    {
        $actual_file_content = <<< TEXT
0:05:40.000,0:05:46.000
Don’t think that you can just ignore them
because they’re not your children or relatives.

0:05:46.000,0:05:51.000
Because every child in our society is
a part of that society
TEXT;
        $actual_internal_format = (new Subtitles())->loadFromString($actual_file_content)->getInternalFormat();
        $expected_internal_format = [[
            'start' => 340,
            'end' => 346,
            'lines' => ['Don’t think that you can just ignore them', 'because they’re not your children or relatives.'],
        ], [
            'start' => 346,
            'end' => 351,
            'lines' => ['Because every child in our society is', 'a part of that society'],
        ]];

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }



    public function testConvertToFile()
    {
        $actual_internal_format = [[
            'start' => 340,
            'end' => 346,
            'lines' => ['Don’t think that you can just ignore them', 'because they’re not your children or relatives.'],
        ], [
            'start' => 346,
            'end' => 351,
            'lines' => ['Because every child in our society is', 'a part of that society'],
        ]];
        $expected_file_content = <<< TEXT
0:05:40.000,0:05:46.000
Don’t think that you can just ignore them
because they’re not your children or relatives.

0:05:46.000,0:05:51.000
Because every child in our society is
a part of that society
TEXT;
        $expected_file_content = str_replace("\r", '', $expected_file_content);

        $actual_file_content = (new Subtitles())->setInternalFormat($actual_internal_format)->content('sbv');

        $this->assertEquals($expected_file_content, $actual_file_content);
    }

    public function testParseMultipleNewLines()
    {
        $actual_file_content = <<< TEXT
0:01:04.927,0:01:06.927
Calm down da Vinci!
OK...


0:01:07.550,0:01:09.550
Yes, yes. On my way.



0:01:24.291,0:01:26.291
Lisa...Mona Lisa.
TEXT;

        $actual_internal_format = (new Subtitles())->loadFromString($actual_file_content)->getInternalFormat();
        $expected_internal_format = [[
            'start' => 64.927,
            'end' => 66.927,
            'lines' => ['Calm down da Vinci!', 'OK...'],
        ], [
            'start' => 67.55,
            'end' => 69.55,
            'lines' => ['Yes, yes. On my way.'],
        ], [
            'start' => 84.291,
            'end' => 86.291,
            'lines' => ['Lisa...Mona Lisa.'],
        ]];

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testInvalidTimeSeparators()
    {
        $string = "0:00:00.120,0:00:05.540
a
b

0:00:07.980.0:00:11.300
c

0:00:25,734,0:00:28,734
d";

        $expected = (new Subtitles())
            ->add(0.12,5.54, ['a', 'b'])
            ->add(7.98, 11.3, 'c')
            ->add(25.734, 28.734, 'd')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual(
            $expected,
            (new Subtitles())->loadFromString($string)->getInternalFormat()
        );
    }

    // @TODO test time above 1 hour

}