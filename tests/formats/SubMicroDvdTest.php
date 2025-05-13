<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SubMicroDvdConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class SubMicroDvdTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesSub()
    {
        $content = file_get_contents('./tests/files/sub_microdvd.sub');
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertTrue(get_class($converter) === SubMicroDvdConverter::class);
    }

    public function testConvertFromSub()
    {
        $sub_path = './tests/files/sub_microdvd.sub';
        $actual = (new Subtitles())->loadFromFile($sub_path)->getInternalFormat();
        $expected = $this->defaultSubtitles()->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual, 0.25);
    }

    public function testConvertToSub()
    {
        $sub_path = './tests/files/sub_microdvd.sub';
        $actual = (new Subtitles())->loadFromFile($sub_path)->content('sub_microdvd');
        $expected = file_get_contents($sub_path);
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testParsesStyles()
    {
        $sub_path = './tests/files/sub_microdvd_with_styles.sub';
        $actual = (new Subtitles())->loadFromFile($sub_path)->getInternalFormat();
        $expected = $this->defaultSubtitles()->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual, 0.25);
    }

    public function testParsesLinesWithoutDotAtTheEnd()
    {
        $content = <<<TEXT
{0}{100}Subtitle line 1
{150}{300}Subtitle line 2
TEXT;
        $actual = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 4.17, 'Subtitle line 1')->add(6.26, 12.51, 'Subtitle line 2')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testUsesSpecifiedFps()
    {
        $content = <<<TEXT
{1}{1}25
{25}{50}Subtitle line 2
TEXT;
        $actual = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'Subtitle line 2')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testParsesSquareBrackets()
    {
        $content = <<<TEXT
[0][100]{y:i}text
TEXT;
        $actual = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 4.17, 'text')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }
}
