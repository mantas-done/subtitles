<?php

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;

class TxtTest extends TestCase {

    use AdditionalAssertions;

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

    public function testConvertingToFormat()
    {
        $actual = (new Subtitles())
            ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.476, 3742.501, ['Very good, Lieutenant.'])
            ->content('txt');

        $this->assertEquals($this->qttxt, $actual);
    }

    public function testConvertingToInternalFormat()
    {
        $actual = Subtitles::load($this->qttxt, 'txt')->getInternalFormat();

        $expected = (new Subtitles())
            ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.476, 3742.501, ['Very good, Lieutenant.'])
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual, 0.07);
    }

}