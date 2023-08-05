<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\TtmlConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class XmlTest extends TestCase
{
    use AdditionalAssertionsTrait;

    public function testConvertFromXml()
    {
        $text = '<?xml version="1.0" encoding="utf-8"?>
<Subtitle>
  <Paragraph>
    <Number>1</Number>
    <StartMilliseconds>0</StartMilliseconds>
    <EndMilliseconds>1000</EndMilliseconds>
    <Text>a</Text>
  </Paragraph>
  <Paragraph>
    <Number>2</Number>
    <StartMilliseconds>1000</StartMilliseconds>
    <EndMilliseconds>2000</EndMilliseconds>
    <Text>b</Text>
  </Paragraph>
</Subtitle>';
        $actual = Subtitles::loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'a')->add(1, 2, 'b')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);

    }
}