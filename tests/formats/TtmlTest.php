<?php

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;

class TtmlTest extends TestCase {

    use AdditionalAssertions;

    public function testConvertFromSrtToTtml()
    {
        $srt_path = './tests/files/srt.srt';
        $ttml_path = './tests/files/ttml.ttml';
        $temporary_ttml_path = './tests/files/tmp/ttml.ttml';

        @unlink($temporary_ttml_path);

        // srt to stl
        Subtitles::convert($srt_path, $temporary_ttml_path);
        $this->assertFileEquals($ttml_path, $temporary_ttml_path);

        unlink($temporary_ttml_path);
    }

    public function testConvertFromTtmlToSrt()
    {
        $srt_path = './tests/files/srt.srt';
        $ttml_path = './tests/files/ttml.ttml';

        // stl to srt
        $ttml_object = Subtitles::load($ttml_path);
        $stl_internal_format = $ttml_object->getInternalFormat();

        $srt_object = Subtitles::load($srt_path);
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

}