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
[01:02:10] Second
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
[00:03:25] Third
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
}