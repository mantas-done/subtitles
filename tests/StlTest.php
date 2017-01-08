<?php

use PHPUnit\Framework\TestCase;
use Done\SubtitleConverter\SubtitleConverter;

class SrtTest extends TestCase {

    public function testInitial()
    {
        $srt_path = './tests/files/srt.srt';
        $stl_path = './tests/files/stl.stl';
        $temporary_stl_path = './tests/files/tmp/stl.stl';

        SubtitleConverter::convert($srt_path, $temporary_stl_path);

        $this->assertFileEquals($stl_path, $temporary_stl_path);

        unlink($temporary_stl_path);
    }

}