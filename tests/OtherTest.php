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

    public function testLastEndTimeIsSmallerThanCurrentStartTime()
    {
        $this->expectException(UserException::class);

        Subtitles::loadFromString('
1
00:00:01,000 --> 00:00:02,000
a

2
00:00:01,000 --> 00:00:04,000
b
        ');
    }
}
