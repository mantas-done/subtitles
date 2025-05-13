<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SrtConverter;
use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;
use PHPUnit\Framework\TestCase;

class SrtTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesSrt()
    {
        $content = file_get_contents('./tests/files/srt.srt');
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertTrue(get_class($converter) === SrtConverter::class);
    }

    public function testRecognizesSrtWithDot()
    {
        $content = <<< TEXT
1
00:00:00.000 --> 00:00:01.000
a
TEXT;
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);

        $this->assertEquals(SrtConverter::class, get_class($converter));
    }

    public function testConvertingFileFromSrtToSrtDoesNotChangeItContent()
    {
        $srt_path = './tests/files/srt.srt';
        $temporary_srt_path = './tests/files/tmp/srt.srt';

        @unlink($temporary_srt_path);

        (new Subtitles())->convert($srt_path, $temporary_srt_path);
        $this->assertFileEqualsIgnoringLineEndings($srt_path, $temporary_srt_path);

        unlink($temporary_srt_path);
    }

    public function testFileToInternalFormat()
    {
        $actual_internal_format = (new Subtitles())->loadFromString(self::fileContent())->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $actual_internal_format);
    }

    public function testConvertToFile()
    {
        $actual_file_content = self::generatedSubtitles()->content('srt');

        $this->assertStringEqualsStringIgnoringLineEndings(self::fileContent(), $actual_file_content);
    }

    public function testRemovesEmptyLines()
    {
        $content = <<< TEXT
1
00:00:01,000 --> 00:00:02,000


2
00:00:03,000 --> 00:00:04,000
Very good, Lieutenant.
TEXT;

        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(3, 4, ['Very good, Lieutenant.'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

    public function testParsesWithWrongArrows()
    {
        $content = <<< TEXT
1
00:00:01,000-->00:00:02,000
a

2
00:00:03,000->00:00:04,000
b
TEXT;

        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, 'a')
            ->add(3, 4, 'b')
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

    public function testParsesClientFile()
    {
        $content = <<< TEXT
1
00:00:01,000-->00:00:02,000
1

TEXT;

        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, ['1'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);

    }

    public function testSkipsInvalidTextAtTheBeggining()
    {
        $content = <<< TEXT
mantas

1
00:00:01,000-->00:00:02,000
a

TEXT;

        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, 'a')
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);

    }

    // ---------------------------------- private ----------------------------------------------------------------------

    private static function fileContent()
    {
        $content = <<< TEXT
1
00:02:17,440 --> 00:02:20,375
Senator, we're making
our final approach into Coruscant.

2
01:02:20,476 --> 01:02:22,501
Very good, Lieutenant.
TEXT;

        return $content;
    }

    private static function generatedSubtitles()
    {
        return (new Subtitles())
            ->add(137.44, 140.375, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.476, 3742.501, ['Very good, Lieutenant.']);
    }

    public function testTimeFormats()
    {
        $content = <<< TEXT
1
00:00:01,100-->00:00:02,200
one

2
00:00:02.200-->00:00:03.300
two

3
00:00:04-->00:00:05
three

4
0:00:05-->0:00:06
four

5
0:00:06,5-->0:00:07,11
five

6
00:00:08:200-->00:00:09:123
six

7
1:2:3,4 --> 1:2:3,5
seven
TEXT;
        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1.1, 2.2, ['one'])
            ->add(2.2, 3.3, ['two'])
            ->add(4, 5, ['three'])
            ->add(5, 6, ['four'])
            ->add(6.5, 7.11, ['five'])
            ->add(8.2, 9.123, ['six'])
            ->add(3723.4, 3723.5, ['seven'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

    public function testParseFileWithTags()
    {
        $content = <<< TEXT
1
00:00:01,000-->00:00:02,000
<font color=”#rrggbb”>one</font>

2
00:00:02.000-->00:00:03.000
<i>two</i>
TEXT;
        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, ['one'])
            ->add(2, 3, ['two'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

    public function testParseFileSubtitleNumbersWithLeadingZeros()
    {
        $content = <<< TEXT
001
00:00:01,000-->00:00:02,000
one

002
00:00:02.000-->00:00:03.000
two
TEXT;
        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, ['one'])
            ->add(2, 3, ['two'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

    public function testWriteUnderstandableMessage()
    {
        $this->expectException(UserException::class);

        $actual = (new Subtitles())->loadFromString('
00:09:01,866

--> 00:09:06,100
THEN, WHEN I LISTENED, HE WAS SAYING ENGLISH
', 'srt')->getInternalFormat();
    }
/* needs more complex code to support this case
    public function testBadTimestamps()
    {
        $this->expectException(UserException::class);

        (new Subtitles())->loadFromString('
00:02:36:13 - 00:02:38:07
Alright, Bogi.

01:12:40,917 --> 01:12:42,875
You were the one who
filed for divorce.
');
    }
*/
    public function testParsesWhiteSpaceOnBlankLine()
    {
        $actual = (new Subtitles())->loadFromString('1
00:00:00,283 --> 00:00:01,133
嗨各位好
' . ' ' . '
2
00:00:01,133 --> 00:00:03,733
歡迎收看硬點茶壇我是福良賣茶人')->getInternalFormat();
        $expected = (new Subtitles())->add(0.283, 1.133, '嗨各位好')->add(1.133, 3.733, '歡迎收看硬點茶壇我是福良賣茶人')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testNoNewLine()
    {
        $content = <<< TEXT
1
00:00:00,000 --> 00:00:01,000
a
2
00:00:01,000 --> 00:00:02,000
b
TEXT;

        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(0, 1, 'a')
            ->add(1, 2, 'b')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected_format, $actual_format);

    }

    public function testConvertToSrtTime()
    {
        $this->assertEquals('00:00:00,001', SrtConverter::internalTimeToSrt(0.001));
        $this->assertEquals('00:00:00,010', SrtConverter::internalTimeToSrt(0.01));
        $this->assertEquals('00:00:00,100', SrtConverter::internalTimeToSrt(0.1));
        $this->assertEquals('00:00:01,000', SrtConverter::internalTimeToSrt(0.9999));
        $this->assertEquals('99:59:59,000', SrtConverter::internalTimeToSrt(359999));
        $this->assertEquals('100:00:00,000', SrtConverter::internalTimeToSrt(360000));
    }
/* needs more complex code to support this case
    public function testBadTimestampWIthFrames()
    {
        $this->expectException(UserException::class);

        $content = <<< TEXT
01:10:43,875 --> 01:11:45,000
Did it come out?1

01:13:27:14 01:13:30:04
Just try please…2
Just go away.3

01:13:43,875 --> 01:13:45,000
Did it come out?4
TEXT;

        (new Subtitles())->loadFromString($content);
    }
*/
    public function testParses0()
    {
        $content = <<< TEXT
1
00:00:01,000-->00:00:02,000
0
TEXT;

        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, '0')
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

    public function testParsesShortTimestamp()
    {
        $content = <<< TEXT
1
00:00:01,000-->00:00:02,000
srt

2	
00:06:57,530 --> 07:03,920
text
TEXT;

        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, 'srt')
            ->add(417.53, 423.92, 'text')
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }

    public function testDoesntParseTimeInText()
    {
        $content = <<< TEXT
1
00:00:01,000-->00:00:02,000
srt

2
00:00:50,360 --> 00:00:53,959
such as three o'clock,
3:30, or maybe 4:00 at the very latest.

TEXT;

        $actual_format = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected_format = (new Subtitles())
            ->add(1, 2, 'srt')
            ->add(50.36, 53.959, ["such as three o'clock,", '3:30, or maybe 4:00 at the very latest.'])
            ->getInternalFormat();
        $this->assertEquals($expected_format, $actual_format);
    }
}