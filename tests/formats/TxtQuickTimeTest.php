<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\TxtQuickTimeConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class TxtQuickTimeTest extends TestCase {

    use AdditionalAssertionsTrait;

    private $qttxt = "{QTtext} {font:Tahoma}
{plain} {size:20}
{timeScale:30}
{width:160} {height:32}
{timestamps:absolute} {language:0}
[00:02:17.11]
Senator, we're making
our final approach into Coruscant.
[00:02:20.09]

[01:02:20.11]
Very good, Lieutenant.
[01:02:22.12]

";

    public function testRecognizesTxtQuictime()
    {
        $content = $this->qttxt;
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertEquals(TxtQuickTimeConverter::class, get_class($converter));
    }

    public function testConvertingToFormat()
    {
        $actual = (new Subtitles())
            ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.476, 3742.501, ['Very good, Lieutenant.'])
            ->content('txt_quicktime');

        $this->assertStringEqualsStringIgnoringLineEndings($this->qttxt, $actual);
    }

    public function testConvertingToInternalFormat()
    {
        $actual = (new Subtitles())->loadFromString($this->qttxt)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.476, 3742.501, ['Very good, Lieutenant.'])
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual, 0.07);
    }

    public function testParsesWithNoEndTimestamp()
    {
        $text = <<<X
{QTtext} {font:Tahoma}
{plain} {size:20}
{timeScale:30}
{width:160} {height:32}
{timestamps:absolute} {language:0}
[00:00:00.00]
a

[00:00:12.00]
b
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 12, 'a')->add(12, 13, 'b')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }
}