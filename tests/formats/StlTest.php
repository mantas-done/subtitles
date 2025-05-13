<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\StlConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class StlTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesStl()
    {
        $content = file_get_contents('./tests/files/stl.stl');
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertTrue(get_class($converter) === StlConverter::class);
    }

    public function testConvertFromSrtToStl()
    {
        $srt_path = './tests/files/srt.srt';
        $stl_path = './tests/files/stl.stl';
        $temporary_stl_path = './tests/files/tmp/stl.stl';

        @unlink($temporary_stl_path);

        // srt to stl
        (new Subtitles())->convert($srt_path, $temporary_stl_path);
        $this->assertFileEqualsIgnoringLineEndings($stl_path, $temporary_stl_path);

        unlink($temporary_stl_path);
    }

    // stl lost some data, so we won't get back our original srt file
    public function testConvertFromStlToSrt()
    {
        $srt_path = './tests/files/srt.srt';
        $stl_path = './tests/files/stl.stl';

        // stl to srt
        $stl_object = (new Subtitles())->loadFromFile($stl_path);
        $stl_internal_format = $stl_object->getInternalFormat();

        $srt_object = (new Subtitles())->loadFromFile($srt_path);
        $srt_internal_format = $srt_object->getInternalFormat();

        // compare both internal formats
        foreach ($srt_internal_format as $block_key => $srt_block) {
            $start_time_diff = abs($srt_block['start'] - $stl_internal_format[$block_key]['start']);
            $this->assertLessThan(0.1, $start_time_diff);

            $end_time_diff = abs($srt_block['end'] - $stl_internal_format[$block_key]['end']);
            $this->assertLessThan(0.1, $end_time_diff);

            foreach ($srt_block['lines'] as $line_key => $srt_line) {
                $this->assertEquals($srt_line, $stl_internal_format[$block_key]['lines'][$line_key]);
            }
        }
    }

    public function testParsesFilesWithComments()
    {
        // checking if no exceptions are thrown
        (new Subtitles())->loadFromFile('./tests/files/stl_with_comments.stl')->content('stl');

        // phpunit complains if no assertions are made
        // @phpstan-ignore-next-line
        $this->assertTrue(true);
    }

    public function testTimesBiggerThan24Hours()
    {
        $text = <<<TEXT
99:00:00:00 , 99:00:01:00 , a
TEXT;

        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(99 * 3600, 99 * 3600 + 1, 'a')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);

        $actual = (new Subtitles())->add(99 * 3600, 99 * 3600 + 1, 'a')->content('stl');
        $this->assertStringContainsString('99:00:01:00', $actual);
    }

    public function testTimesBiggerThan99HoursThrowException()
    {
        $this->expectException(\Exception::class);

        $subtitles = new Subtitles();
        $subtitles->add(3600 * 123 - 1, 3600 * 123, 'text');
        $subtitles->content('stl');
    }

    public function testClientFile()
    {
        $text = '
$FadeOut			=	0
$TapeOffset			=	TRUE
 
$HorzAlign	=	Center
'."00:00:00:00\t,\t00:00:01:00\t,\ta
00:00:01:00\t,\t00:00:02:00\t,\tb
";

        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'a')->add(1, 2, 'b')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }
}