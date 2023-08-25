<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\VttConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class VttTest extends TestCase
{

    use AdditionalAssertionsTrait;

    public function testRecognizesVtt()
    {
        $content = file_get_contents('./tests/files/vtt.vtt');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === VttConverter::class);
    }

    public function testDoesntTakeFilesMentioningVtt()
    {
        $content = 'something 
about WEBVTT';
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class !== VttConverter::class);
    }

    public function testConvertFromVttToSrt()
    {
        $vtt_path = './tests/files/vtt.vtt';
        $srt_path = './tests/files/srt.srt';

        $expected = (new Subtitles())->loadFromFile($vtt_path)->content('srt');
        $actual = file_get_contents($srt_path);
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testConvertFromVttToSrtComplex()
    {
        $vtt_path = './tests/files/vtt_to_srt.vtt';
        $srt_path = './tests/files/vtt_to_srt.srt';

        $expected = (new Subtitles())->loadFromFile($vtt_path)->content('srt');
        $actual = file_get_contents($srt_path);
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testConvertFromSrtToVtt()
    {
        $srt_path = './tests/files/srt.srt';
        $vtt_path = './tests/files/vtt.vtt';

        $expected = file_get_contents($vtt_path);
        $actual = (new Subtitles())->loadFromFile($srt_path)->content('vtt');

        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testConvertFromSrtToVttComplex()
    {
        $srt_path = './tests/files/srt_to_vtt.srt';
        $vtt_path = './tests/files/srt_to_vtt.vtt';

        $expected = file_get_contents($vtt_path);
        $actual = (new Subtitles())->loadFromFile($srt_path)->content('vtt');

        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testFileToInternalFormat()
    {
        $vtt_path = './tests/files/vtt_with_name.vtt';
        $expected_internal_format = [[
            'start' => 9,
            'end' => 11,
            'lines' => ['We are in New York City'],
            'speakers' => ['Roger Bingham'],
        ]];

        $actual_internal_format = Subtitles::loadFromFile($vtt_path)->getInternalFormat();
        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testFileToInternalFormatComplex()
    {
        $vtt_path = './tests/files/vtt_complex.vtt';
        $expected_internal_format = [
            [
                'start' => 9,
                'end' => 11,
                'lines' => ['Line 1'],
                'speakers' => ['speaker1'],
            ],
            [
                'start' => 12,
                'end' => 13,
                'lines' => ['Line 2'],
            ],
            [
                'start' => 14,
                'end' => 15,
                'lines' => ['Line 3', 'Line 4'],
                'speakers' => ['speaker1', 'speaker2'],
            ],
            [
                'start' => 16,
                'end' => 17,
                'lines' => ['Line 5', 'Line 6'],
            ],
            [
                'start' => 18,
                'end' => 19,
                'lines' => ['Line 7', 'Line 8'],
                // It should not be ['speaker1', '']
                'speakers' => ['speaker1'],
                'vtt_cue_settings' => 'line:0 position:20% size:60% align:start'
            ],
            [
                'start' => 20,
                'end' => 21,
                'lines' => ['Line 9', 'Line 10'],
                'speakers' => ['', 'speaker2'],
            ]
        ];

        $actual_internal_format = Subtitles::loadFromFile($vtt_path)->getInternalFormat();
        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testConvertingFileFromVttToVttDoesNotChangeItContent()
    {
        $vtt_path = './tests/files/vtt_complex.vtt';
        $temporary_vtt_path = './tests/files/tmp/vtt_complex.vtt';

        @unlink($temporary_vtt_path);

        Subtitles::convert($vtt_path, $temporary_vtt_path);
        $vtt_internal_format = Subtitles::loadFromFile($vtt_path)->getInternalFormat();
        $vtt_tmp_internal_format = Subtitles::loadFromFile($temporary_vtt_path)->getInternalFormat();

        $this->assertInternalFormatsEqual($vtt_internal_format, $vtt_tmp_internal_format);
        unlink($temporary_vtt_path);
    }

    public function testParsesFileWithCue()
    {
        $input_vtt_file_content = <<< TEXT
WEBVTT

1
00:00:00.000 --> 00:00:01.000
a

some text allowed in vtt that is not shown
00:00:01.000 --> 00:00:02.000
b
b

something
00:00:02.000 --> 00:00:03.000
c

TEXT;

        $actual = Subtitles::loadFromString($input_vtt_file_content)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'a')->add(1, 2, ['b', 'b'])->add(2, 3, 'c')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testParsesFileWithCueComplex()
    {
        $input_vtt_file_content = <<< TEXT
WEBVTT

00:00:00.400 --> 00:00:00.900 something
<v speaker1>a</v>

some text allowed in vtt that is not shown
00:00:01.000 --> 00:00:02.000
<v speaker1>b</v>
c

something
00:00:02.000 --> 00:00:03.000 something
d
<v speaker2> e</v>

00:00:03.000 --> 00:00:04.000
f
g

00:00:04.000 --> 00:00:05.000
<v speaker2>h</v>
<v speaker1>i</v>

TEXT;

        $vtt_path = './tests/files/vtt2.vtt';
        $actual = Subtitles::loadFromString($input_vtt_file_content)->getInternalFormat();
        $expected = Subtitles::loadFromFile($vtt_path)->add(1, 2, ['speaker1' => 'b', 'c'])->add(2, 3, ['d', 'speaker2' => 'e'], ['vtt_cue_settings' => 'something'])->add(3, 4, ['f', 'g'])->add(4, 5, ['speaker2' => 'h', 'speaker1' => 'i'])->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testParsesFileWithMissingText()
    {
        $vtt_path = './tests/files/vtt_with_missing_text.vtt';
        $actual = (new Subtitles())->loadFromFile($vtt_path)->getInternalFormat();
        $expected = [
            [
                'start' => 0,
                'end' => 1,
                'lines' => [
                    'one',
                ],
            ], [
                'start' => 2,
                'end' => 3,
                'lines' => [
                    'three',
                ],
            ]
        ];
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testFileContainingMultipleNewLinesBetweenBlocks()
    {
        $given = <<< TEXT
WEBVTT

00:00:00.000 --> 00:00:01.000
text1





00:00:01.000 --> 00:00:02.000
text2
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0, 1, 'text1')
            ->add(1, 2, 'text2')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParsesFileWithStyles()
    {
        $given = file_get_contents('./tests/files/vtt_with_styles.vtt');
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0.0, 10.0, 'Hello world.', ['vtt_cue_settings' => 'position:50% line:15% align:middle'])
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParsesFileWithHtml()
    {
        $given = file_get_contents('./tests/files/vtt_with_html.vtt');
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0.0, 10.0, 'Sur les playground, ici à Montpellier')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParsesFileWithoutHoursInTimestamp()
    {
        $given = file_get_contents('./tests/files/vtt_without_hours_in_timestamp.vtt');
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();

        $expected = (new Subtitles())
            ->add(0.0, 10.0, "I've spent nearly two decades")
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParsesFileWithMultipleNewLines()
    {
        $given = file_get_contents('./tests/files/vtt_with_multiple_new_lines.vtt');
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0.0, 1.0, ['one', 'two'])
            ->add(2.0, 3.0, 'three')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParseFileWithMetadata()
    {
        $given = <<< TEXT
WEBVTT
X-TIMESTAMP-MAP=LOCAL:00:00:00.000,MPEGTS:0

00:00:00.000 --> 00:00:01.000
text1
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, 'text1')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testParseFileWithSpacesBetweenTimestamps()
    {
        $given = <<< TEXT
WEBVTT

     00:00:00.100    -->    00:00:01.100     
text1
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0.1, 1.1, 'text1')
            ->getInternalFormat();

        $this->assertEquals($expected, $actual);
    }

    public function testTimeFormats()
    {
        $given = <<< TEXT
WEBVTT

00:00:01.10 --> 00:00:02.50
one
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(1.1, 2.5, 'one')
            ->getInternalFormat();
        $this->assertEquals($expected, $actual);
    }

    public function testTimeFormats2()
    {
        $given = <<< TEXT
WEBVTT

00:03.30 --> 00:04.40
two
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(3.3, 4.4, 'two')
            ->getInternalFormat();
        $this->assertEquals($expected, $actual);
    }

    public function testTimeFormats3()
    {
        $given = <<< TEXT
WEBVTT

1:00:00.000 --> 1:00:01.000 something
three
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(3600.0, 3601.0, 'three', ['vtt_cue_settings' => 'something'])
            ->getInternalFormat();
        $this->assertEquals($expected, $actual);
    }

    public function testParsesFileWithExtraNewLines()
    {
        $given = <<< TEXT
WEBVTT



00:00:01.10 --> 00:00:02.50

one


00:00:03.30 --> 00:00:04.40

two
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(1.1, 2.5, 'one')
            ->add(3.3, 4.4, 'two')
            ->getInternalFormat();
        $this->assertEquals($expected, $actual);
    }

    public function testParsesIncorrectTimestampWithComma()
    {
        $given = <<< TEXT
WEBVTT

00:00:01,01 --> 00:00:02.02
a
TEXT;
        $actual = (new Subtitles())->loadFromString($given)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(1.01, 2.02, 'a')
            ->getInternalFormat();
        $this->assertEquals($expected, $actual);
    }
}
