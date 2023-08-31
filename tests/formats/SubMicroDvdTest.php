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
        $converter = Helpers::getConverterByFileContent($content);
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
}
