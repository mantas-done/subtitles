<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\AssConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class AssTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testAss()
    {
        $content = file_get_contents('./tests/files/ass.ass');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === AssConverter::class);
    }

    public function testConvertFromAssToInternalFormat()
    {
        $ass_path = './tests/files/ass.ass';
        $srt_path = './tests/files/srt.srt';

        $actual = (new Subtitles())->load($ass_path)->getInternalFormat();
        $expected = (new Subtitles())->load($srt_path)->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromAssToSrt()
    {
        $ass_path = './tests/files/ass.ass';
        $srt_path = './tests/files/srt.srt';

        $actual = (new Subtitles())->load($srt_path)->content('ass');
        $expected = file_get_contents($ass_path);

        $actual = str_replace("\r", "", $actual);
        $expected = str_replace("\r", "", $expected);

        $this->assertEquals($expected, $actual);
    }

    public function testConvertFromAssWithDifferentFormatToInternalFormat()
    {
        $ass_path = './tests/files/ass_different_format.ass';
        $srt_path = './tests/files/srt.srt';

        $actual = (new Subtitles())->load($ass_path)->getInternalFormat();
        $expected = (new Subtitles())->load($srt_path)->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromAssWithDifferentFormatToInternalFormat2()
    {
        $ass_path = './tests/files/ass_different_format2.ass';
        $srt_path = './tests/files/srt.srt';

        $actual = (new Subtitles())->load($ass_path)->getInternalFormat();
        $expected = (new Subtitles())->load($srt_path)->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromAssWithDifferentFormatToInternalFormat3()
    {
        $ass_path = './tests/files/ass_different_format3.ass';
        $actual = (new Subtitles())->load($ass_path)->getInternalFormat();

        $expected = (new Subtitles())->add(0, 10, 'The quick brown fox jumps over a lazy dog.')->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }
}
