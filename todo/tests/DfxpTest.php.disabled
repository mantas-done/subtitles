<?php

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;

class DfxpTest extends TestCase {

    use AdditionalAssertions;

    public function testConvertFromSrtToDfxp()
    {
        $srt_path = './tests/files/srt.srt';
        $dfxp_path = './tests/files/dfxp.dfxp';
        $temporary_dfxp_path = './tests/files/tmp/dfxp.dfxp';

        @unlink($temporary_dfxp_path);

        // srt to stl
        Subtitles::convert($srt_path, $temporary_dfxp_path);
        $this->assertFileEquals($dfxp_path, $temporary_dfxp_path);

        unlink($temporary_dfxp_path);
    }

    public function testConvertFromDfxpToSrt()
    {
        $srt_path = './tests/files/srt.srt';
        $dfxp_path = './tests/files/dfxp.dfxp';

        // stl to srt
        $dfxp_object = Subtitles::load($dfxp_path);
        $stl_internal_format = $dfxp_object->getInternalFormat();

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