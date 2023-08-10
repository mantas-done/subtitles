<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\TtmlConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class TtmlTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesTtml()
    {
        $content = file_get_contents('./tests/files/ttml.ttml');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertEquals(TtmlConverter::class, $converter::class);
    }

    public function testConvertFromSrtToTtml()
    {
        $srt_path = './tests/files/srt.srt';
        $ttml_path = './tests/files/ttml.ttml';
        $temporary_ttml_path = './tests/files/tmp/ttml.ttml';

        @unlink($temporary_ttml_path);

        // srt to stl
        Subtitles::convert($srt_path, $temporary_ttml_path);
        $this->assertFileEqualsIgnoringLineEndings($ttml_path, $temporary_ttml_path);

        unlink($temporary_ttml_path);
    }

    public function testConvertFromTtmlToSrt()
    {
        $srt_path = './tests/files/srt.srt';
        $ttml_path = './tests/files/ttml.ttml';

        // stl to srt
        $ttml_object = Subtitles::loadFromFile($ttml_path);
        $actual = $ttml_object->getInternalFormat();

        $srt_object = Subtitles::loadFromFile($srt_path);
        $expected = $srt_object->getInternalFormat();

        $this->assertInternalFormatsEqual($actual, $expected);
    }

    public function testParses2()
    {
        $ttml_path = './tests/files/ttml2.ttml';
        $actual = Subtitles::loadFromFile($ttml_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 2, 'Hello I am your first line.')
            ->add(2, 4, ['I am your second captions', 'but with two lines.'])
            ->add(4, 6, ['Je suis le troisième sous-titre.'])
            ->add(6, 8, ['I am another caption with Bold and Italic styles.'])
            ->add(8, 10, ['I am the last caption displayed in red and centered.'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testDuplicatedElementIdsParse()
    {
        $this->expectException(UserException::class);

        $ttml_path = './tests/files/ttml_with_duplicated_element_ids.ttml';
        $actual = Subtitles::loadFromFile($ttml_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, 'First line.')
            ->add(1, 2, ['Second line.'])
            ->add(2, 3, ['Third line.'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testTimeParseWithFpsAndMultiplierGiven()
    {
        $this->expectException(UserException::class);

        $ttml_path = './tests/files/ttml_with_fps_and_multiplier_given.ttml';
        $actual = Subtitles::loadFromFile($ttml_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(15.015, 17.684, 'First line.')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testOutputsAccurateTimestamp()
    {
        $actual = (new Subtitles())->add(0.3, 8.456, 'test')->content('ttml');
        $this->assertStringContainsString('"0.3s"', $actual);
        $this->assertStringContainsString('"8.456s"', $actual);
    }

    /**
     * @dataProvider timeFormatProvider
     */
    public function testDifferentTimeFormats($ttml_time, $seconds, $fps)
    {
        $internal_seconds = TtmlConverter::ttmlTimeToInternal($ttml_time, $fps);
        $this->assertEquals($internal_seconds, $seconds);
    }

    public static function timeFormatProvider()
    {
        return [
            ['360f', 12, 30],
            ['135f', 2.25, 60],
            ['00:00:10', 10, null],
            ['00:00:5.100', 5.1, null],
            ['55s', 55, null],
        ];
    }

    public function testParseWithMultipleDivs()
    {
        $ttml_path = './tests/files/ttml_with_multiple_divs.ttml';
        $actual = Subtitles::loadFromFile($ttml_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(1.464, 2.423, ["Senator, we're making", 'our final approach into Coruscant.'])
            ->add(2.423, 5.432, ['Very good, Lieutenant.'])
            ->add(10.886, 10.928, ['CLUB SHOU-TIME'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml()
    {
        $text = '<?xml version="1.0" encoding="utf-8"?>
<Subtitle>
  <Paragraph>
    <Number>1</Number>
    <StartMilliseconds>0</StartMilliseconds>
    <EndMilliseconds>1000</EndMilliseconds>
    <Text>a<br/>b</Text>
  </Paragraph>
  <Paragraph>
    <Number>2</Number>
    <StartMilliseconds>1000</StartMilliseconds>
    <EndMilliseconds>2000</EndMilliseconds>
    <Text>c</Text>
  </Paragraph>
</Subtitle>';
        $actual = Subtitles::loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, ['a', 'b'])->add(1, 2, 'c')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml2()
    {
        $text = <<<X
<?xml version="1.0" encoding="UTF-8"?>
<tt xml:lang='en' xmlns='http://www.w3.org/2006/10/ttaf1' xmlns:tts='http://www.w3.org/2006/10/ttaf1#style'>
<head></head>
<body>
<div xml:id="captions">
<p begin="00:00:00.000" end="00:00:01.000">a<br />b</p>
<p begin="00:00:02.123" end="00:00:03.321">c</p>
</div>
</body>
</tt>
X;
        $actual = Subtitles::loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, ['a', 'b'])->add(2.123, 3.321, 'c')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml3()
    {
        $text = <<<X
<?xml version="1.0"?>
<tt
	xmlns="http://www.w3.org/ns/ttml" xml:lang="en"
	xmlns:ttp="http://www.w3.org/ns/ttml#parameter"
	xmlns:tts="http://www.w3.org/ns/ttml#styling">
	<head/>
	<body region="subtitleArea">
		<p begin="0.0s" dur="2.0s">test1</p>
		<p begin="5.38s" dur="6.0s">test2</p>
	</body>
</tt>

X;
        $actual = Subtitles::loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 2, 'test1')->add(5.38, 11.38, 'test2')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml4()
    {
        $text = <<<X
<?xml version="1.0"?>
<tt
	xmlns="http://www.w3.org/ns/ttml" xml:lang="en"
	xmlns:ttp="http://www.w3.org/ns/ttml#parameter"
	xmlns:tts="http://www.w3.org/ns/ttml#styling">
	<head/>
	<body region="subtitleArea">
		<p begin="0.0s" dur="">test1</p>
		<p begin="5.38s" dur="">test2</p>
	</body>
</tt>

X;
        $actual = Subtitles::loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 5.38, 'test1')->add(5.38, 6.38, 'test2')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

}