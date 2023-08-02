<?php

namespace Tests;

use Done\Subtitles\Code\UserException;
use PHPUnit\Framework\TestCase;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;

class OtherTest extends TestCase
{
    use AdditionalAssertionsTrait;

    public function testEndTimeIsBiggerThanStart()
    {
        $this->expectException(UserException::class);

        Subtitles::loadFromString('
1
00:00:02,000 --> 00:00:01,000
a
        ');
    }

    public function testTimesOverlapOver10Seconds()
    {
        $this->expectException(UserException::class);

        Subtitles::loadFromString('
1
00:00:01,000 --> 00:00:40,000
a

2
00:00:20,000 --> 00:00:50,000
b
        ');
    }

    public function testFixesUpTo10SecondsTimeOverlap()
    {
        $actual = Subtitles::loadFromString('
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
        $actual = Subtitles::loadFromString('
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
}
