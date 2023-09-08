<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\CsvConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class CsvTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesCsvFormat()
    {
        $csv = 'Start,End,Text
137.44,140.375,"Senator, we\'re making our final approach into Coruscant."
3740.476,3742.501,"Very good, Lieutenant."';
        $converter = Helpers::getConverterByFileContent($csv);
        $this->assertTrue(get_class($converter) === CsvConverter::class, get_class($converter));
    }

    public function testDoesntSelectNonCsvFormat()
    {
        $csv = '0:00:15.1,0:00:17.4 Herkese merhaba.
0:00:17.4,0:00:20.7 Bu videoda Microsoft office ürünlerinin';
        $converter = Helpers::getConverterByFileContent($csv);
        $this->assertTrue(get_class($converter) !== CsvConverter::class, get_class($converter));
    }

    public function testFileToInternalFormat()
    {
        $csv = 'Start,End,Text
137.44,140.375,"Senator, we\'re making our final approach into Coruscant."
3740.476,3742.501,"Very good, Lieutenant."';
        $actual_internal_format = Subtitles::loadFromString($csv)->getInternalFormat();
        $expected_internal_format = (new Subtitles())
        ->add(137.44, 140.375, ['Senator, we\'re making our final approach into Coruscant.'])
        ->add(3740.476, 3742.501, ['Very good, Lieutenant.'])->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testConvertToFile()
    {
        $actual_csv_string = (new Subtitles())
        ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
        ->add(3740.476, 3742.501, ['Very good, Lieutenant.'])->content('csv');
        $expected_csv_string = 'Start,End,Text
137.44,140.375,"Senator, we\'re making our final approach into Coruscant."
3740.476,3742.501,"Very good, Lieutenant."
';
        $expected_csv_string = str_replace("\r", "", $expected_csv_string);

        $this->assertEquals($expected_csv_string, $actual_csv_string);
    }

    public function testClientAnsiFile()
    {
        $actual = Subtitles::loadFromFile('./tests/files/csv_ansi.csv')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(1, 2, 'Oh! Can I believe my eyes!')
            ->add(2, 3, ['If Heaven and earth,', 'if mortals and angels'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    /**
     * @dataProvider differentContentSeparatorProvider
     */
    public function testDifferentContentSeparators($string)
    {
        $actual_internal_format = Subtitles::loadFromString($string)->getInternalFormat();
        $expected_internal_format = (new Subtitles())
            ->add(1, 2, ['Oh! Can I believe my eyes!'])
            ->add(2, 3, ['If Heaven and earth.'])->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public static function differentContentSeparatorProvider()
    {
        $original_string = 'Start,End,Text
00:00:1,00:00:2,Oh! Can I believe my eyes!
00:00:2,00:00:3,If Heaven and earth.';

        $strings = [];
        foreach (CsvConverter::$allowedSeparators as $separator) {
            $strings[] = str_replace(',', $separator, $original_string);
        }

        return [$strings];
    }

    public function testParseFileWithSingleTimestamp()
    {
        $string = <<< TEXT
00:00:01    One
00:00:02    Two
TEXT;
        $actual_internal_format = Subtitles::loadFromString($string)->getInternalFormat();
        $expected_internal_format = (new Subtitles())
            ->add(1, 2, ['One'])
            ->add(2, 3, ['Two'])->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testExtraEmptyLine() // client file
    {
        $string = <<< TEXT
00:00:01,One
,
00:00:02,Two
TEXT;
        $actual_internal_format = Subtitles::loadFromString($string)->getInternalFormat();
        $expected_internal_format = (new Subtitles())
            ->add(1, 2, ['One'])
            ->add(2, 3, ['Two'])->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testAdditionalColumns()
    {
        $string = <<< TEXT
Start Time,End Time,Text,Layer ID
00:00:08:00,00:00:13:00,"abc",1
00:00:20:00,00:00:24:00,def,1
TEXT;
        $actual_internal_format = Subtitles::loadFromString($string)->getInternalFormat();
        $expected_internal_format = (new Subtitles())
            ->add(8, 13, ['abc'])
            ->add(20, 24, ['def'])->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testNoText()
    {
        $this->expectException(UserException::class);

        $string = "
0\t681.9946
0.02\t308.0328
";
        Subtitles::loadFromString($string)->getInternalFormat();
    }

    public function testNoDecimal()
    {
        $string = <<< TEXT
0,1,text
1,2,text
TEXT;
        $actual_internal_format = Subtitles::loadFromString($string)->getInternalFormat();
        $expected_internal_format = (new Subtitles())
            ->add(0, 1, 'text')
            ->add(1, 2, 'text')->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }
}