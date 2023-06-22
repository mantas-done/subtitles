<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\TtmlConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class TtmlTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesTtml()
    {
        $content = file_get_contents('./tests/files/ttml.ttml');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertEquals(TtmlConverter::class, $converter::class);
    }

    public function testConvertFromSrtToTtml()
    {
        $srt_path = './tests/files/srt.srt';
        $ttml_path = './tests/files/ttml.ttml';
        $temporary_ttml_path = './tests/files/tmp/ttml.ttml';

        @unlink($temporary_ttml_path);

        // srt to stl
        Subtitles::convert($srt_path, $temporary_ttml_path);
        $this->assertFileEqualsIgnoringLineEndings($ttml_path, $temporary_ttml_path);

        unlink($temporary_ttml_path);
    }

    public function testConvertFromTtmlToSrt()
    {
        $srt_path = './tests/files/srt.srt';
        $ttml_path = './tests/files/ttml.ttml';

        // stl to srt
        $ttml_object = Subtitles::loadFromFile($ttml_path);
        $actual = $ttml_object->getInternalFormat();

        $srt_object = Subtitles::loadFromFile($srt_path);
        $expected = $srt_object->getInternalFormat();

        $this->assertInternalFormatsEqual($actual, $expected);
    }

    public function testParses2()
    {
        $ttml_path = './tests/files/ttml2.ttml';
        $actual = Subtitles::loadFromFile($ttml_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 10, 'Hello I am your first line.')
            ->add(2, 10, ['I am your second captions', 'but with two lines.'])
            ->add(4, 10, ['Je suis le troisième sous-titre.'])
            ->add(6, 10, ['I am another caption with Bold and Italic styles.'])
            ->add(8, 10, ['I am the last caption displayed in red and centered.'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testDifferentTimestampParse()
    {
        $ttml_path = './tests/files/ttml_with_mixed_timestamps.ttml';
        $actual = Subtitles::loadFromFile($ttml_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 10, 'First line.')
            ->add(11.2, 13.3, ['Second line.'])
            ->add(15, 18, ['Third line.'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testDuplicatedElementIdsParse()
    {
        $ttml_path = './tests/files/ttml_with_duplicated_element_ids.ttml';
        $actual = Subtitles::loadFromFile($ttml_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, 'First line.')
            ->add(1, 2, ['Second line.'])
            ->add(2, 3, ['Third line.'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

}