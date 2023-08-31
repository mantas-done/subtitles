<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SccConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class SccTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesScc()
    {
        $content = file_get_contents('./tests/files/scc.scc');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue(get_class($converter) === SccConverter::class);
    }

    public function testShortensTextIfItIsTooLong()
    {
        $content = (new Subtitles())->add(1, 2, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')->content('scc');
        $actual = Subtitles::loadFromString($content)->getInternalFormat();
        $this->assertEquals('aaaaaaaaaaaa', $actual[0]['lines'][0]);
    }

    public function testTimestampsAccountForTheDataSendingTime()
    {
        $actual = (new Subtitles())->add(1, 2, 'aaaa')->content('scc');
        $expected = "Scenarist_SCC V1.0

00:00:00;20\t94ae 94ae 9420 9420 9470 9470 6161 6161 942f 942f

00:00:01;26\t942c 942c

";
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
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

    public function testFromSccMoreCharacters()
    {
        $string = "Scenarist_SCC V1.0

00:00:00;00\t94ae 94ae 9420 9420 13d0 13d0 91b0 1370 1370 9220 94d0 94d0 6180 91b0 9470 9470 6180 9220 942f 942f

00:00:10;00\t942c 942c

";
        $actual = (new Subtitles())->loadFromString($string)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 10, ['®', 'Á', 'a®', 'aÁ'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual, 1000); // 1000 - don't check timestamps
    }

    public function testParsesUppercaseLetters()
    {
        $string = "Scenarist_SCC V1.0

00:00:00;00\t94ae 94AE 9420 9420 13d0 13d0 91b0 1370 1370 9220 94d0 94d0 6180 91b0 9470 9470 6180 9220 942f 942f

00:00:10;00\t942c 942c

";
        $actual = (new Subtitles())->loadFromString($string)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 10, ['®', 'Á', 'a®', 'aÁ'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual, 1000); // 1000 - don't check timestamps
    }

    public function testSplitLongLines()
    {
        $array = [
            "This is a long line that needs to be split",
            "Short line"
        ];

        $actual = SccConverter::splitLongLines($array);
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

        $actual = SccConverter::splitLongLines($array);
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

        $actual = SccConverter::splitLongLines($array);
        $expected = [
            "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
            "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
            "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
        ];
        $this->assertEquals($expected, $actual);
    }

    public function testDoesntAddStopLineIfTimesAreTouching()
    {
        $expected = "Scenarist_SCC V1.0

00:00:00;20\t94ae 94ae 9420 9420 9470 9470 ef6e e580 942f 942f

00:00:01;20\t94ae 94ae 9420 9420 9470 9470 f4f7 ef80 942f 942f

00:00:02;26\t942c 942c

";
        $actual = (new Subtitles())->add(1, 2, 'one')->add(2.01, 3, 'two')->content('scc');
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
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
        $actual = SccConverter::sccTimeToInternal('00:59:56:12', 0);
        $this->assertEqualsWithDelta(3600.0, $actual, 0.01);
    }

    public function testConvertsNonDropFrameTimeWithText()
    {
        $actual = SccConverter::sccTimeToInternal('00:59:56:12', 30);
        $this->assertEqualsWithDelta(3600.5, $actual, 0.01);
    }

    public function testConvertsDropFrameTime()
    {
        $actual = SccConverter::sccTimeToInternal('01:00:00;00', 0);
        $this->assertEquals(3600.0, $actual);
    }

    public function testConvertsDropFrameTimeWithText()
    {
        $actual = SccConverter::sccTimeToInternal('01:00:00;00', 30);
        $this->assertEqualsWithDelta(3600.5, $actual, 0.001);
    }

    public function testInternalTimeToScc()
    {
        $actual = SccConverter::internalTimeToScc(3600, 0);
        $this->assertEquals('01:00:00;00', $actual, 0.001);
    }

    public function testInternalTimeToSccTimeWithText()
    {
        $actual = SccConverter::internalTimeToScc(3600, 30);
        $this->assertEquals('00:59:59;15', $actual, 0.001);
    }

    public function testSpaceBetweenBlocks()
    {
        $scc = "Scenarist_SCC V1.0

00:00:00;21	94ae 94ae 9420 9420 9470  9470 6180 942f 942f

00:00:01;26	942c 942c";
        $actual = Subtitles::loadFromString($scc)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'a')->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual, 0.1);
    }
}