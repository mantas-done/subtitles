<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\LrcConverter;
use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;
use PHPUnit\Framework\TestCase;

class LrcTest extends TestCase
{
    use AdditionalAssertionsTrait;

    public function testRecognizeLrc()
    {
        $content = file_get_contents('./tests/files/lrc.lrc');
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertTrue(get_class($converter) === LrcConverter::class);
    }

    public function testNotLrc() // let other converter handle invalid lrc
    {
        $content = '[00:02:35]
Tere Vaaste Falak Se
Main Chaand Launga
Solah Satrah Sitaare
Sang Baandh Launga

[00:02:51]
Tere Vaaste Falak Se
Main Chaand Launga
Solah Satrah Sitaare
Sang Baandh Launga';
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertTrue(get_class($converter) !== LrcConverter::class);
    }

    public function testNotLrcWhenOnlyFewInlineTextLines()
    {
        // timestamps are mostly on their own line (TxtConverter style), only a couple have inline text
        $content = <<< TEXT
[00:08.6]
first line on separate line

[00:23.2]
second line on separate line

[00:38.1]
third line on separate line

[00:47.1] inline text here
TEXT;
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertTrue(get_class($converter) !== LrcConverter::class);

        $actual = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $this->assertCount(4, $actual);
        $this->assertEquals(8.6, $actual[0]['start']);
        $this->assertEquals('first line on separate line', $actual[0]['lines'][0]);
        $this->assertEquals(23.2, $actual[1]['start']);
        $this->assertEquals(38.1, $actual[2]['start']);
        $this->assertEquals(47.1, $actual[3]['start']);
    }

    public function testParsesLrc()
    {
        $expected = (new Subtitles())->loadFromFile('./tests/files/lrc.lrc')->getInternalFormat();
        $actual = (new Subtitles())
            ->add(8.62, 9.64, ['First things first'])
            ->add(9.64, 11.66, ['I\'ma say all the words inside my head'])
            ->add(11.66, 18.68, ['I\'m fired up and tired of the way that things have been, oh ooh'])
            ->add(18.68, 22.63, ['The way that things have been, oh ooh'])
            ->add(22.63, 23.63, ['Second thing second'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testTimeFormats()
    {
        $given = <<< TEXT
[01:01.10] First
[01:02.10] Second
[02:01] Third
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(61.1, 62.1, 'First')
            ->add(62.1, 121, 'Second')
            ->add(121, 122, 'Third')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParsesHoursTimeFormat()
    {
        $given = '[01:02:15.42]Some lyrics';
        $actual = (new LrcConverter())->fileContentToInternalFormat($given, $given, true);
        $expected = [
            ['start' => 3735.42, 'end' => 3736.42, 'lines' => ['Some lyrics']],
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testParseLrcWithPositiveTimeOffset()
    {
        $given = <<< TEXT
[offset:+500]
[00:08.62]First things first
[00:09.64]I'ma say all the words inside my head
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(8.12, 9.14, 'First things first')
            ->add(9.14, 10.14, 'I\'ma say all the words inside my head')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParseLrcWithNegativeTimeOffset()
    {
        $given = <<< TEXT
[offset:-250]
[00:08.62]First things first
[00:09.64]I'ma say all the words inside my head
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(8.87, 9.89, 'First things first')
            ->add(9.89, 10.89, 'I\'ma say all the words inside my head')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParseGroupedLines()
    {
        $given = <<< TEXT
[00:01.10] First
[00:02.20][00:05.00] [grouped]
[00:03.25] Third
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();

        $expected = (new Subtitles())
            ->add(1.1, 2.2, 'First')
            ->add(2.2, 3.25, '[grouped]')
            ->add(3.25, 5, 'Third')
            ->add(5, 6, '[grouped]')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testNegativeStartTime()
    {
        $this->expectException(UserException::class);

        $given = <<< TEXT
[offset:500]
[00:00.00]a
TEXT;
        (new Subtitles())->loadFromString($given)->getInternalFormat();
    }

    public function testExport()
    {
        $expected = <<< TEXT
[00:01.00] one
[00:03.00] two
TEXT;

        $actual = (new Subtitles())
            ->add(1, 2, 'one')
            ->add(3, 4, 'two')
            ->getInternalFormat();

        $actual = (new Subtitles())->setInternalFormat($actual)->content('lrc');
        $this->assertStringEqualsStringIgnoringLineEndings(trim($expected), trim($actual));
    }

    public function testExportUsesHoursForLongerTimestamps()
    {
        $actual = (new Subtitles())
            ->add(3661.2, 3662.2, 'one')
            ->getInternalFormat();

        $actual = (new Subtitles())->setInternalFormat($actual)->content('lrc');

        $this->assertStringContainsString('[61:01.20] one', $actual);
    }
}