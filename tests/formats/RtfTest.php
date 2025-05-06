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
        $actual = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'word')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testClientFileWithBackslashes()
    {
        $content = file_get_contents('./tests/files/rtf2.rtf');
        $actual = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(223, 229, 'Reflecting back on the nineteen forties, fifties and sixties')
            ->add(230, 240, 'During those times the elders back home were grieving,')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }
}