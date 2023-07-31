<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\TxtConverter;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class TxtTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testConvertToFile()
    {
        $generated_subtitles = (new Subtitles())
            ->add(0, 1, ['Senator, we\'re making our', 'final approach into Coruscant.'])
            ->add(1, 2, ['Very good, Lieutenant.']);

        $actual_file_content = $generated_subtitles->content('txt');

        $expected = <<< TEXT
Senator, we're making our final approach into Coruscant.
Very good, Lieutenant.
TEXT;
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual_file_content);
    }

    public function testNoTimestamps()
    {
        $content = <<< TEXT
Senator, we're making our final approach into Coruscant.
Very good, Lieutenant.
TEXT;
        $actual_internal_format = Subtitles::loadFromString($content)->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $actual_internal_format);
    }

    public function testSingleTimestampOnTheSameLine()
    {
        $content = <<< TEXT
00:00:00 Senator, we're making our final approach into Coruscant.
00:00:01 Very good, Lieutenant.
TEXT;
        $actual_internal_format = Subtitles::loadFromString($content)->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $actual_internal_format);
    }

    public function testSingleTimestampOnDifferentLine()
    {
        $content = <<< TEXT
00:00 
Senator, we're making our final approach into Coruscant.
00:01 
Very good, Lieutenant.
TEXT;
        $actual_internal_format = Subtitles::loadFromString($content)->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $actual_internal_format);
    }

    public function testIncompleteSrt()
    {
        $content = <<< TEXT
1
00:00:01 -->
a
b

2
00:00:2 -->
c

TEXT;
        $actual = Subtitles::loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, ['a', 'b'])->add(2, 3, 'c')->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testExcessiveNewLinesWithTwoTimestamps()
    {
        $content = <<< TEXT
1

00:00:01 --> 00:00:02

a

b


2

00:00:3 --> 00:00:4

c

TEXT;
        $actual = Subtitles::loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, ['a', 'b'])->add(3, 4, 'c')->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testTimestamps()
    {
        $content = <<< TEXT
01:23 
a
01:23:45
b
01:23:45,001
c
01:23:46.2
d
01:23:47:20
e
5030.81
f
TEXT;
        $actual = Subtitles::loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(83, 5025, 'a')
            ->add(5025, 5025.001, 'b')
            ->add(5025.001, 5026.2, 'c')
            ->add(5026.2, 5027.8, 'd')
            ->add(5027.8, 5030.81, 'e')
            ->add(5030.81, 5031.81, 'f')
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testClientFile()
    {
        $content = <<< TEXT
1
00:10.000--> 00:11.900:
One
1

00:12.000--> 00:12.900:
Two
2 
TEXT;
        $actual = Subtitles::loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(10, 11.9, ['One', '1'])
            ->add(12, 12.9, ['Two', '2'])
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testNotTimestampInTheMiddleOfText()
    {
        $parts = TxtConverter::getLineParts('The sun rises at 6:03 a.m.');
        $this->assertEquals($parts, [
            'start' => '',
            'end' => '',
            'text' => 'The sun rises at 6:03 a.m.',
        ]);
    }

    public function testNotATimestampIfInTheMiddleOfTheText()
    {
        $actual = Subtitles::loadFromString('
            a
            b 00:00
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'a')->add(1, 2, 'b 00:00')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    // ---------------------------------- private ----------------------------------------------------------------------

    private static function generatedSubtitles()
    {
        return (new Subtitles())
            ->add(0, 1, ['Senator, we\'re making our final approach into Coruscant.'])
            ->add(1, 2, ['Very good, Lieutenant.']);
    }

}