<?php

namespace Tests\formats;

use Done\Subtitles\Code\Converters\LrcConverter;
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
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === LrcConverter::class);
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
}