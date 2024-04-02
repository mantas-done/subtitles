<?php

namespace Formats;

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class RtfTest extends TestCase
{

    use AdditionalAssertionsTrait;

    public function testParsesRtfFile()
    {
        $content = file_get_contents('./tests/files/rtf.rtf');
        $actual = Subtitles::loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'word')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

}