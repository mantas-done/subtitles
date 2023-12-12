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
Senator, we're making our
final approach into Coruscant.

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
        $this->assertInternalFormatsEqual(
            (new Subtitles())->add(83, 84, 'a')->getInternalFormat(),
            Subtitles::loadFromString('
01:23
a
')->getInternalFormat());

        $this->assertInternalFormatsEqual(
            (new Subtitles())->add(5026, 5027.001, 'b')->getInternalFormat(),
            Subtitles::loadFromString('
01:23:46
b
')->getInternalFormat());

        $this->assertInternalFormatsEqual(
            (new Subtitles())->add(5027.001, 5028.001, 'c')->getInternalFormat(),
            Subtitles::loadFromString('
01:23:47,001
c
')->getInternalFormat());

        $this->assertInternalFormatsEqual(
            (new Subtitles())->add(5028.2, 5029.2, 'd')->getInternalFormat(),
            Subtitles::loadFromString('
01:23:48.2
d
')->getInternalFormat());

        $this->assertInternalFormatsEqual(
            (new Subtitles())->add(5029.769, 5030.769, 'e')->getInternalFormat(),
            Subtitles::loadFromString('
01:23:49:20
e
')->getInternalFormat());

        $this->assertInternalFormatsEqual(
            (new Subtitles())->add(0.984, 1.984, 'e')->getInternalFormat(),
            Subtitles::loadFromString('
00:00:00:60
e
')->getInternalFormat());

        $this->assertInternalFormatsEqual(
            (new Subtitles())->add(5050.81, 5051.81, 'f')->getInternalFormat(),
            Subtitles::loadFromString('
5050.81
f
')->getInternalFormat());

        $this->assertInternalFormatsEqual(
            (new Subtitles())->add(1103.474, 1152.99, ',Speaker 2,""So at any time"""')->getInternalFormat(),
            Subtitles::loadFromString('
"00:18:23:46,00:19:12:96,Speaker 2,""So at any time"""
')->getInternalFormat());
    }

    public function testClientFile()
    {
        $content = <<< TEXT
1
00:10.000--> 00:11.900:
One
1

2
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

    public function testNotATimestampIfInTheMiddleOfTheText()
    {
        $actual = Subtitles::loadFromString('
            a
            b 00:00
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'a')->add(1, 2, 'b 00:00')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    // ignore all the content before the timestamp
    public function testNoException()
    {
        $actual = Subtitles::loadFromString('
            a
            00:03 b 
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(3, 4, 'b')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testNoExceptionFromClientFile()
    {
        $actual = Subtitles::loadFromString('
            a
            b
            00:00:00
            c
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'c')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }


    public function testIfFileWithoutTimestampsDoNotReturnTimestamp()
    {
        $actual = Subtitles::loadFromString('
a
b
c
d
e
10,000 rounds of ammunition
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, 'a')
            ->add(1, 2, 'b')
            ->add(2, 3, 'c')
            ->add(3, 4, 'd')
            ->add(4, 5, 'e')
            ->add(5, 6, '10,000 rounds of ammunition')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testCorrectlyParsesNumbers()
    {
        $actual = Subtitles::loadFromString('
00:00 50,000 a
00:01 b
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, '50,000 a')
            ->add(1, 2, 'b')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testLinesTogetherAreLeftConnected()
    {
        $actual = Subtitles::loadFromString('
a
b

c

d
e
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, ['a', 'b'])
            ->add(1, 2, 'c')
            ->add(2, 3, ['d', 'e'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testTextHasTimestampLikeNumber()
    {
        $actual = Subtitles::loadFromString('
23:19
a
23:25
103.06 meters
23:29
c
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(1399, 1405, 'a')
            ->add(1405, 1409, '103.06 meters')
            ->add(1409, 1410, 'c')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testFirstLineWithTimestampSecondAfter()
    {
        $actual = Subtitles::loadFromString('
01:01 a
b
c
d
01:02 e
f
01:03 g
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(61, 62, ['a', 'b', 'c', 'd'])
            ->add(62, 63, ['e', 'f'])
            ->add(63, 64, 'g')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testNoException2()
    {
        $actual = Subtitles::loadFromString('
00:00:15:00 - 00:00:20:00
a 40,50 b
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(15, 20, 'a 40,50 b')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);


    }

    public function testNoException3()
    {
        $actual = Subtitles::loadFromString('
01:00
a

10:00
b
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(60, 120, 'a')
            ->add(600, 601, 'b')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);


    }

    public function testDoubleTimestamps()
    {
        $actual = Subtitles::loadFromString('
00:01
00:02
a
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(2, 3, 'a')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testReordersTime()
    {
        $actual = Subtitles::loadFromString('
00:02
b

00:01
a
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'a')->add(2, 3, 'b')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testRemovesHtml()
    {
        $actual = Subtitles::loadFromString('
00:01
<b>a</b>
        ')->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'a')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testRecognizesLongerTimestampAfterTheShorter()
    {
        $actual = Subtitles::loadFromString('
00:00
a
00:01
b
00:02
c
00:00:03
d
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, 'a')
            ->add(1, 2, 'b')
            ->add(2, 3, 'c')
            ->add(3, 4, 'd')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);

    }

    public function testLongTimestampCountIsGreaterThanShort()
    {
        $actual = Subtitles::loadFromString('
00:00
a
00:01
b
00:00:02
c
00:00:03
d
00:00:04
e
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, 'a')
            ->add(1, 2, 'b')
            ->add(2, 3, 'c')
            ->add(3, 4, 'd')
            ->add(4, 5, 'e')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);

    }

    public function testParsesTimestampsWhenTheyAreIdentical()
    {
        $actual = TxtConverter::getLineParts('00:00 00:00 a', 1, 2);
        $this->assertEquals([
            'start' => '00:00',
            'end' => '00:00',
            'text' => 'a',
        ], $actual);
    }

    // ---------------------------------- private ----------------------------------------------------------------------

    private static function generatedSubtitles()
    {
        return (new Subtitles())
            ->add(0, 1, ['Senator, we\'re making our final approach into Coruscant.'])
            ->add(1, 2, ['Very good, Lieutenant.']);
    }

}