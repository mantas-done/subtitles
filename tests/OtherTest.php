<?php

namespace Tests;

use Done\Subtitles\Code\Exceptions\DisableStrictSuggestionException;
use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;
use PHPUnit\Framework\TestCase;

class OtherTest extends TestCase
{
    use AdditionalAssertionsTrait;

    public function testEndTimeIsBiggerThanStart()
    {
        $this->expectException(UserException::class);

        (new Subtitles())->loadFromString('
1
00:00:02,000 --> 00:00:01,000
a
        ');
    }

    public function testTimesOverlapOver10Seconds()
    {
        $this->expectException(UserException::class);

        (new Subtitles())->loadFromString('
1
00:00:01,000 --> 00:01:40,000
a

2
00:00:20,000 --> 00:01:50,000
b
        ');
    }

    public function testFixesUpTo10SecondsTimeOverlap()
    {
        $actual = (new Subtitles())->loadFromString('
1
00:00:01,000 --> 00:00:02,000
a

2
00:00:01,500 --> 00:00:04,000
b
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(1, 1.5, 'a')->add(1.5, 4, 'b')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testMergeIfStartEquals()
    {
        $actual = (new Subtitles())->loadFromString('
3
00:00:03,000 --> 00:00:04,000
c

2
00:00:02,000 --> 00:00:03,000
b

1
00:00:02,000 --> 00:00:02,500
a
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(2, 3, ['a', 'b'])->add(3, 4, 'c')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testExceptionIfCaptionTooLong()
    {
        $this->expectException(UserException::class);

        (new Subtitles())->loadFromString('
1
00:00:00,000 --> 00:05:01,000
a
        ');
    }

    public function testRemovesEmptySubtitles()
    {
        $actual = (new Subtitles())->loadFromString('
[Script Info]

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
Dialogue: 0,0:21:33.39,0:23:07.52,Default,,0,0,0,,
Dialogue: 0,0:21:41.41,0:21:44.20,사랑에 애태우며,,0,0,0,,test
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(1301.41, 1304.20, 'test')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testRemoveEmptyLines()
    {
        $actual = (new Subtitles())->loadFromString('
[Script Info]

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
Dialogue: 0,0:00:01.00,0:00:02.00,Default,,0,0,0,,test\N
Dialogue: 0,0:00:03.00,0:00:04.00,Default,,0,0,0,,test
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'test')->add(3, 4, 'test')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testDetectUtf16Encoding()
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            $this->markTestSkipped('Skipping test on PHP versions earlier than 8.0');
        }

        $actual = (new Subtitles())->loadFromFile('./tests/files/utf16.srt')->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'ترجمه و تنظيم زيرنويس')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testStrictModeEnabled()
    {
        $this->expectException(DisableStrictSuggestionException::class);
        (new Subtitles())->loadFromString('00:00:00 01:00:00 help')->getInternalFormat();
    }

    public function testStrictModeDisabled()
    {
        $actual = (new Subtitles())->loadFromString('
00:00:01 00:00:02 test
00:00:00 01:00:00 help
', false)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'test')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testNotStrictModeRemovesAllBlocks()
    {
        $actual = (new Subtitles())->loadFromString('00:00:00 01:00:00 help', false)->getInternalFormat();
        $this->assertInternalFormatsEqual([], $actual);
    }
}
