<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SubConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\AdditionalAssertionsTrait;

class SubTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesSub()
    {
        $content = file_get_contents('./tests/files/sub.sub');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === SubConverter::class);
    }

    public function testConvertFromSubToSrt()
    {
        $sub_path = './tests/files/sub.sub';

        $expected = <<<TEXT
1
00:02:17,400 --> 00:02:20,400
Senator, we're making
our final approach into Coruscant.

2
01:02:20,500 --> 01:02:22,500
Very good, Lieutenant.
TEXT;

        $actual = (new Subtitles())->load($sub_path)->content('srt');

        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testConvertFromSrtToSub()
    {
        $srt_path = './tests/files/srt.srt';
        $sub_path = './tests/files/sub.sub';

        $expected = file_get_contents($sub_path);
        $actual = (new Subtitles())->load($srt_path)->content('sub');

        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }
}
