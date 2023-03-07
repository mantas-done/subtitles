<?php

use Done\Subtitles\Subtitles;
use Done\Subtitles\Test\Helpers\AdditionalAssertions;
use PHPUnit\Framework\TestCase;

class TxtTest extends TestCase
{

    use AdditionalAssertions;

    public function testConvertingToFormat()
    {
        $qtext_path = './tests/files/qtext.qt.txt';

        $actual = (new Subtitles())
            ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.476, 3742.501, ['Very good, Lieutenant.'])
            ->content('txt');

        $expected = file_get_contents($qtext_path);
        $this->assertEquals($expected, $actual);
    }

    public function testConvertingToInternalFormat()
    {
        $qtext_path = './tests/files/qtext.qt.txt';
        $data = file_get_contents($qtext_path);

        $actual = Subtitles::load($data, 'txt')->getInternalFormat();

        $expected = (new Subtitles())
            ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.476, 3742.501, ['Very good, Lieutenant.'])
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual, 0.07);
    }
}
