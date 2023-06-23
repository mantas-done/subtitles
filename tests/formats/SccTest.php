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
        $this->assertTrue($converter::class === SccConverter::class);
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

00:00:00:20\t94ae 94ae 9420 9420 9470 9470 6161 6161 942f 942f

00:00:01:26\t942c 942c

";
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testParsesScc()
    {
        $expected = (new Subtitles())->loadFromFile('./tests/files/scc.scc')->getInternalFormat();
        $actual = (new Subtitles())
        ->add(137.4, 140.4, ['Senator, we\'re making', 'our final approach into', 'Coruscant.'])
        ->add(3740.5, 3742.5, ['Very good, Lieutenant.'])
        ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testFromSccMoreCharacters()
    {
        $string = "Scenarist_SCC V1.0

00:00:00:00\t94ae 94ae 9420 9420 13d0 13d0 91b0 1370 1370 9220 94d0 94d0 6180 91b0 9470 9470 6180 9220 942f 942f

00:00:01:00\t942c 942c

";
        $actual = (new Subtitles())->loadFromString($string)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, ['®', 'Á', 'a®', 'aÁ'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
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

00:00:00:20\t94ae 94ae 9420 9420 9470 9470 ef6e e580 942f 942f

00:00:01:20\t94ae 94ae 9420 9420 9470 9470 f4f7 ef80 942f 942f

00:00:02:26\t942c 942c

";
        $actual = (new Subtitles())->add(1, 2, 'one')->add(2.01, 3, 'two')->content('scc');
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
    }

    public function testIgnoreErasedDisplayMemoryCodeAtStart()
    {
        $string = "Scenarist_SCC V1.0

00:00:02:00\t942c 942c

00:00:04:00\t94ae 94ae 9420 9420 9476 9476 97a1 97a1 c8e5 ecec ef80 942f 942f

00:00:06:00\t942c 942c

";
        $actual = (new Subtitles())->loadFromString($string)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(4, 6, ['Hello'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);

    }

    public function testLastSubtitleEndTimeCorrection()
    {
        $string = "Scenarist_SCC V1.0

00:00:02:00\t942c 942c

00:00:04:00\t94ae 94ae 9420 9420 9476 9476 97a1 97a1 c8e5 ecec ef80 942f 942f

";
        $subtitle_set = (new Subtitles())->loadFromString($string)->getInternalFormat();
        $last_subtitle = end($subtitle_set);

        $this->assertEquals($last_subtitle['end'], $last_subtitle['start'] + 1);
    }
}