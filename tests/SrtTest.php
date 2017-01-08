<?php

use PHPUnit\Framework\TestCase;
use Done\SubtitleConverter\SubtitleConverter;

class SrtTest extends TestCase {

    public function testConvertingFileFromSrtToSrtDoesntChangeItContent()
    {
        $srt_path = './tests/files/srt.srt';
        $temporary_srt_path = './tests/files/tmp/srt.srt';

        @unlink($temporary_srt_path);

        SubtitleConverter::convert($srt_path, $temporary_srt_path);
        $this->assertFileEquals($srt_path, $temporary_srt_path);

        unlink($temporary_srt_path);
    }

}