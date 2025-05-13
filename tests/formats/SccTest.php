<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SccConverter;
use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;
use PHPUnit\Framework\TestCase;

class SccTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesScc()
    {
        $content = file_get_contents('./tests/files/scc.scc');
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertTrue(get_class($converter) === SccConverter::class);
    }

    public function testParsesScc()
    {
        $expected = (new Subtitles())->loadFromFile('./tests/files/scc.scc')->getInternalFormat();
        $actual = (new Subtitles())
        ->add(140, 145, ['Senator, we\'re making', 'our final approach into', 'Coruscant.'])
        ->add(3740, 3745, ['Very good, Lieutenant.'])
        ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual, 0.01);
    }

    public function testSplitLongLines()
    {
        $array = [
            "This is a long line that needs to be split",
            "Short line"
        ];

        $actual = SccConverter::splitLongLines($array, []);
        $expected = [
            "This is a long line that needs",
            "to be split",
            "Short line"
        ];
        $this->assertEquals($expected, $actual);

    }

    public function testSplitLongLines2()
    {
        $array = [
            "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa a",
        ];

        $actual = SccConverter::splitLongLines($array, []);
        $expected = [
            "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
            "a"
        ];
        $this->assertEquals($expected, $actual);
    }

    public function testSplitLongLines3()
    {
        $array = [
            "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
        ];

        $actual = SccConverter::splitLongLines($array, []);
        $expected = [
            "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
            "aaaaaaaaaaaaaaaaaaaaaaaaaaaaa...",
        ];
        $this->assertEquals($expected, $actual);
    }

    public function testIgnoreErasedDisplayMemoryCodeAtStart()
    {
        $string = "Scenarist_SCC V1.0

00:00:02;00\t942c 942c

00:00:04;00\t94ae 94ae 9420 9420 9476 9476 97a1 97a1 c8e5 ecec ef80 942f 942f

00:00:06;00\t942c 942c

";
        $actual = (new Subtitles())->loadFromString($string)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(4, 6, ['Hello'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual, 1000); // do not check timestamps

    }

    public function testLastSubtitleEndTimeCorrection()
    {
        $string = "Scenarist_SCC V1.0

00:00:02;00\t942c 942c

00:00:04;00\t94ae 94ae 9420 9420 9476 9476 97a1 97a1 c8e5 ecec ef80 942f 942f

";
        $subtitle_set = (new Subtitles())->loadFromString($string)->getInternalFormat();
        $last_subtitle = end($subtitle_set);

        $this->assertEquals($last_subtitle['end'], $last_subtitle['start'] + 1);
    }

    public function testConvertsNonDropFrameTime()
    {
        $actual = SccConverter::sccTimeToInternal('00:59:56:12', 0, 29.97);
        $this->assertEqualsWithDelta(3600.0, $actual, 0.01);
    }

    public function testConvertsNonDropFrameTimeWithText()
    {
        $actual = SccConverter::sccTimeToInternal('00:59:56:12', 30, 29.97);
        $this->assertEqualsWithDelta(3600.5, $actual, 0.01);
    }

    public function testConvertsDropFrameTime()
    {
        $actual = SccConverter::sccTimeToInternal('01:00:00;00', 0, 29.97);
        $this->assertEquals(3600.0, $actual);
    }

    public function testConvertsDropFrameTimeWithText()
    {
        $actual = SccConverter::sccTimeToInternal('01:00:00;00', 30, 29.97);
        $this->assertEqualsWithDelta(3600.5, $actual, 0.001);
    }

    public function testInternalTimeToScc()
    {
        $actual = SccConverter::internalTimeToScc(1, 20, 29.97, false);
        $this->assertEquals('00:00:00;21', $actual, 0.001);
    }

    public function testInternalTimeToSccTimeWithText()
    {
        $actual = SccConverter::internalTimeToScc(2, 2, 29.97, false);
        $this->assertEquals('00:00:02;00', $actual, 0.001);
    }

    public function testSpaceBetweenBlocks()
    {
        $scc = "Scenarist_SCC V1.0

00:00:00;21	94ae 94ae 9420 9420 9470  9470 6180 942f 942f

00:00:01;26	942c 942c";
        $actual = (new Subtitles())->loadFromString($scc)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'a')->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual, 0.1);
    }

    public function testCustomCharacters()
    {
        $scc = (new Subtitles())
            ->add(1, 2, ['cœurs défoncés'])
            ->content('scc');
        $internal = (new Subtitles())->loadFromString($scc)->getInternalFormat();
        $this->assertEquals('coeurs défoncés', $internal[0]['lines'][0]);
    }

    public function testSpecialChars()
    {
        $codes = SccConverter::lineToCodes('®');
        $this->assertEquals('91b0', $codes);

        $codes = SccConverter::lineToCodes('a®');
        $this->assertEquals('6180 91b0', $codes);

        $codes = SccConverter::lineToCodes('aa®');
        $this->assertEquals('6161 91b0', $codes);


        $lines = SccConverter::sccToLines('91b0');
        $this->assertEquals(['®'], $lines);

        $lines = SccConverter::sccToLines('6180 91b0');
        $this->assertEquals(['a®'], $lines);

        $lines = SccConverter::sccToLines('6161 91b0');
        $this->assertEquals(['aa®'], $lines);
    }

    public function testExtendedChars()
    {
        $codes = SccConverter::lineToCodes('ß');
        $this->assertEquals('7380 1334', $codes);

        $codes = SccConverter::lineToCodes('aß');
        $this->assertEquals('6173 1334', $codes);

        $codes = SccConverter::lineToCodes('aaß');
        $this->assertEquals('6161 7380 1334', $codes);


        $lines = SccConverter::sccToLines('7380 1334');
        $this->assertEquals(['ß'], $lines);

        $lines = SccConverter::sccToLines('6173 1334');
        $this->assertEquals(['aß'], $lines);

        $lines = SccConverter::sccToLines('6161 7380 1334');
        $this->assertEquals(['aaß'], $lines);
    }

    public function testNoTimeToSendFullTextInNonStrictMode()
    {
        $scc = (new Subtitles())->add(1, 2, 'A')->add(2, 3, '123456789 123456789 123456789 123456789 123456789 123456789 ')->content('scc');
        $actual = (new Subtitles())->loadFromString($scc)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'A')->add(2, 3, ['123456789 123456789 123456789', '123456789 1234..'])->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual, 0.04);
    }

    public function testNoTimeToSendFullTextInStrictMode()
    {
        $this->expectException(UserException::class);

        (new Subtitles())->add(1, 2, 'A')->add(2, 3, ['123456789 123456789 123456789 123456789 123456789 123456789 '])->content('scc', ['strict' => true]);
    }

    public function testSplitsLineOver32Characters()
    {
        $scc = (new Subtitles())->add(2, 3, '123456789 123456789 123456789 123456789 123456789 123456789 ')->content('scc');
        $actual = (new Subtitles())->loadFromString($scc)->getInternalFormat();
        $expected = (new Subtitles())->add(2, 3, ['123456789 123456789 123456789', '123456789 123456789 123456789'])->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual, 0.05);
    }

    public function testThrowsExceptionInStrictModeIfLineIsOver32Characters()
    {
        $this->expectException(UserException::class);

        (new Subtitles())->add(2, 3, '123456789 123456789 123456789 123456789 123456789 123456789 ')->content('scc', ['strict' => true]);
    }
}