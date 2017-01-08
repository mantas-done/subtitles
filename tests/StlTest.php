<?php

use PHPUnit\Framework\TestCase;
use Done\SubtitleConverter\SubtitleConverter;

class StlTest extends TestCase {

    public function testConvertFromSrtToStl()
    {
        $srt_path = './tests/files/srt.srt';
        $stl_path = './tests/files/stl.stl';
        $temporary_stl_path = './tests/files/tmp/stl.stl';

        @unlink($temporary_stl_path);

        // srt to stl
        SubtitleConverter::convert($srt_path, $temporary_stl_path);
        $this->assertFileEquals($stl_path, $temporary_stl_path);

        unlink($temporary_stl_path);
    }

    // stl lost some data, so we won't get back our original srt file
    public function testConvertFromStlToSrt()
    {
        $srt_path = './tests/files/srt.srt';
        $stl_path = './tests/files/stl.stl';
        $temporary_srt_path = './tests/files/tmp/srt.srt';
        $temporary_stl_path = './tests/files/tmp/stl.stl';

        @unlink($temporary_srt_path);
        @unlink($temporary_stl_path);

        // stl to srt
        $stl_object = SubtitleConverter::convert($stl_path, $temporary_srt_path);
        $stl_internal_format = $stl_object->getInternalFormat();

        $srt_object = SubtitleConverter::convert($srt_path, $temporary_stl_path);
        $srt_internal_format = $srt_object->getInternalFormat();

        unlink($temporary_srt_path);
        unlink($temporary_stl_path);

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