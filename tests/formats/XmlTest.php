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

    public function testConvertFromXml2()
    {
        $text = <<<X
<?xml version="1.0" encoding="UTF-8"?>
<tt xml:lang='en' xmlns='http://www.w3.org/2006/10/ttaf1' xmlns:tts='http://www.w3.org/2006/10/ttaf1#style'>
<head></head>
<body>
<div xml:id="captions">
<p begin="00:00:00.000" end="00:00:01.000">a<br />b</p>
<p begin="00:00:02.123" end="00:00:03.321">c</p>
</div>
</body>
</tt>
X;
        $actual = Subtitles::loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, ['a', 'b'])->add(2.123, 3.321, 'c')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }
}