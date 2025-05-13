<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\TtmlConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TtmlTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesTtml()
    {
        $content = file_get_contents('./tests/files/ttml.ttml');
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertEquals(TtmlConverter::class, get_class($converter));
    }

    public function testConvertFromSrtToTtml()
    {
        $srt_path = './tests/files/srt.srt';
        $ttml_path = './tests/files/ttml.ttml';
        $temporary_ttml_path = './tests/files/tmp/ttml.ttml';

        @unlink($temporary_ttml_path);

        // srt to stl
        (new Subtitles())->convert($srt_path, $temporary_ttml_path);
        $this->assertFileEqualsIgnoringLineEndings($ttml_path, $temporary_ttml_path);

        unlink($temporary_ttml_path);
    }

    public function testEscapesSpecialCharacters()
    {
        $expected = (new Subtitles())->add(0, 1, '&\'"< >')->getInternalFormat();

        $ttml = (new Subtitles())->add(0, 1, '&\'"< >')->content('ttml');
        $actual = (new Subtitles())->loadFromString($ttml)->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromTtmlToSrt()
    {
        $srt_path = './tests/files/srt.srt';
        $ttml_path = './tests/files/ttml.ttml';

        // stl to srt
        $ttml_object = (new Subtitles())->loadFromFile($ttml_path);
        $actual = $ttml_object->getInternalFormat();

        $srt_object = (new Subtitles())->loadFromFile($srt_path);
        $expected = $srt_object->getInternalFormat();

        $this->assertInternalFormatsEqual($actual, $expected);
    }

    public function testParses2()
    {
        $ttml_path = './tests/files/ttml2.ttml';
        $actual = (new Subtitles())->loadFromFile($ttml_path)->getInternalFormat();
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
        $ttml_path = './tests/files/ttml_with_duplicated_element_ids.ttml';
        $actual = (new Subtitles())->loadFromFile($ttml_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, 'First line.')
            ->add(1, 2, ['Second line.'])
            ->add(2, 3, ['Third line.'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testTimeParseWithFpsAndMultiplierGiven()
    {
        $ttml_path = './tests/files/ttml_with_fps_and_multiplier_given.ttml';
        $actual = (new Subtitles())->loadFromFile($ttml_path)->getInternalFormat();
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

    #[DataProvider('timeFormatProvider')]
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
            ['8500', 8.5, null],
            ['03:45.702', 225.702, null],
            ['1500ms', 1.5, null],
        ];
    }

    public function testParseWithMultipleDivs()
    {
        $ttml_path = './tests/files/ttml_with_multiple_divs.ttml';
        $actual = (new Subtitles())->loadFromFile($ttml_path)->getInternalFormat();
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
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, ['a', 'b'])->add(1, 2, 'c')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromUtf16WhenItReallyIsUtf8()
    {
        $text = '<?xml version="1.0" encoding="utf-16"?>
<Subtitle>
  <Paragraph>
    <Number>1</Number>
    <StartMilliseconds>0</StartMilliseconds>
    <EndMilliseconds>1000</EndMilliseconds>
    <Text>a</Text>
  </Paragraph>
</Subtitle>';
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'a')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromUtf16WhenItReallyIsUtf8DifferentQuotesAndUppercase()
    {
        $text = '<?xml version="1.0" encoding=\'UTF-16\'?>
<Subtitle>
  <Paragraph>
    <Number>1</Number>
    <StartMilliseconds>0</StartMilliseconds>
    <EndMilliseconds>1000</EndMilliseconds>
    <Text>a</Text>
  </Paragraph>
</Subtitle>';
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 1, 'a')->getInternalFormat();
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
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
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
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
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
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 5.38, 'test1')->add(5.38, 6.38, 'test2')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testParsesDCSubtitles()
    {
        $text = <<<X
<?xml version="1.0" encoding="UTF-8"?>
<DCSubtitle Version="1.0">
   <!--Monal 2008 - v6.1.1.1-->
   <!--Sync - "FFP" - 00:00:00:00-->
   <!--Running Speed - 24fps-->
   <SubtitleID>5c652e39-9bf1-4c67-9793-0ec7c8948e83</SubtitleID>
   <MovieTitle>MAL DE PIERRES</MovieTitle>
   <ReelNumber>1AB</ReelNumber>
   <Language>English</Language>
   <LoadFont Id="Font1" URI="1AB-dci.ttf" />
   <Font Effect="border" Size="42" Id="Font1">
      <Subtitle SpotNumber="1" TimeIn="00:00:51:000" TimeOut="00:00:52:000">
         <Text HAlign="center" HPosition="0.0" VAlign="bottom" VPosition="7">
            <Font>Here.</Font>
         </Text>
      </Subtitle>
      <Subtitle SpotNumber="2" TimeIn="00:01:30:020" TimeOut="00:01:31:031">
         <Text HAlign="center" HPosition="0.0" VAlign="bottom" VPosition="7">
            <Font>No, not now.</Font>
         </Text>
      </Subtitle>
   </Font>
</DCSubtitle>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(51, 52, 'Here.')->add(90.02, 91.031, 'No, not now.')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testParsesDCSubtitlesNoFont()
    {
        $text = <<<X
<?xml version="1.0" encoding="UTF-8"?>
<DCSubtitle Version="1.0">
   <!--Monal 2008 - v6.1.1.1-->
   <!--Sync - "FFP" - 00:00:00:00-->
   <!--Running Speed - 24fps-->
   <SubtitleID>5c652e39-9bf1-4c67-9793-0ec7c8948e83</SubtitleID>
   <MovieTitle>MAL DE PIERRES</MovieTitle>
   <ReelNumber>1AB</ReelNumber>
   <Language>English</Language>
   <LoadFont Id="Font1" URI="1AB-dci.ttf" />
  <Subtitle SpotNumber="1" TimeIn="00:00:51:000" TimeOut="00:00:52:000">
     <Text HAlign="center" HPosition="0.0" VAlign="bottom" VPosition="7">
        <Font>Here.</Font>
     </Text>
  </Subtitle>
  <Subtitle SpotNumber="2" TimeIn="00:01:30:020" TimeOut="00:01:31:031">
     <Text HAlign="center" HPosition="0.0" VAlign="bottom" VPosition="7">
        <Font>No, not now.</Font>
     </Text>
  </Subtitle>
</DCSubtitle>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(51, 52, 'Here.')->add(90.02, 91.031, 'No, not now.')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml5()
    {
        $text = <<<X
<?xml version="1.0" encoding="UTF-8"?>
<!-- Profile: EBU-TT-D-Basic-DE -->
<tt:tt
	xmlns:tt="http://www.w3.org/ns/ttml"
	xmlns:tts="http://www.w3.org/ns/ttml#styling"
	xmlns:xs="http://www.w3.org/2001/XMLSchema"
	xmlns:ttm="http://www.w3.org/ns/ttml#metadata"
	xmlns:ttp="http://www.w3.org/ns/ttml#parameter"
	xmlns:ebuttdt="urn:ebu:tt:datatypes"
	xmlns:ebuttm="urn:ebu:tt:metadata"
	xmlns:ebutts="urn:ebu:tt:style"
	xmlns:ebuttExt="urn:ebu:tt:extension" ttp:timeBase="media" ttp:cellResolution="50 30" xml:lang="de">
	<tt:head>
		<tt:metadata>
			<ebuttm:documentMetadata>
				<ebuttm:documentEbuttVersion>v1.0</ebuttm:documentEbuttVersion>
			</ebuttm:documentMetadata>
		</tt:metadata>
		<tt:styling>
			<tt:style xml:id="S1" tts:fontSize="160%" tts:fontFamily="Verdana, Arial, Tiresias" tts:lineHeight="125%" />
			<tt:style xml:id="S2" tts:textAlign="center" />
			<tt:style xml:id="S3" tts:color="#ffffff" tts:backgroundColor="#000000c2" />
			<tt:style xml:id="S4" tts:color="#ffff00" tts:backgroundColor="#000000c2" />
		</tt:styling>
		<tt:layout>
			<tt:region xml:id="R1" tts:origin="10% 83%" tts:extent="80% 7%" />
			<tt:region xml:id="R2" tts:origin="10% 77%" tts:extent="80% 13%" />
			<tt:region xml:id="R3" tts:origin="10% 70%" tts:extent="80% 7%" />
			<tt:region xml:id="R4" tts:origin="10% 63%" tts:extent="80% 14%" />
		</tt:layout>
	</tt:head>
	<tt:body>
		<tt:div style="S1">
			<tt:p xml:id="C1" region="R1" style="S2" begin="00:00:00.000" end="00:00:02.080">
				<tt:span style="S3">Badewetter an der Ostsee.</tt:span>
			</tt:p>
			<tt:p xml:id="C2" region="R2" style="S2" begin="00:00:02.200" end="00:00:05.400">
				<tt:span style="S3">Jetzt im Sommer</tt:span>
				<tt:br />
				<tt:span style="S3">hat das Meer um die 19°C.</tt:span>
			</tt:p>
        </tt:div>
	</tt:body>
</tt:tt>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 2.08, 'Badewetter an der Ostsee.')->add(2.2, 5.4, ['Jetzt im Sommer', 'hat das Meer um die 19°C.'])->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml6()
    {
        $text = <<<X
<?xml version="1.0" encoding="UTF-8"?>
<SubtitleReel xmlns="http://www.smpte-ra.org/schemas/428-7/2014/DCST">
  <Id>urn:uuid:da9af7ab-3375-4082-be52-1fb4e426b1f2</Id>
  <ContentTitleText>crazy</ContentTitleText>
  <IssueDate>2023-08-01T13:43:38-01:00</IssueDate>
  <ReelNumber>1</ReelNumber>
  <Language>de</Language>
  <EditRate>25 1</EditRate>
  <TimeCodeRate>25</TimeCodeRate>
  <StartTime>10:00:00:00</StartTime>
  <LoadFont ID="Font1">urn:uuid:45bf5399-4b88-45c3-9200-0a42147b4bed</LoadFont>
  <SubtitleList>
    <Font ID="Font1" Color="FFEBEBEB" Effect="border" EffectColor="FF0A0A0A" Feather="yes" EffectSize="1.0" Size="42" Weight="normal">
      <Subtitle SpotNumber="1" TimeIn="00:00:13:00" TimeOut="00:00:15:00">
        <Text Halign="center" Valign="bottom" Hposition="0.0" Vposition="8.0">*Lied: "Feeling Good" von Nina Simone*</Text>
      </Subtitle>
      <Subtitle SpotNumber="2" TimeIn="00:00:15:00" TimeOut="00:00:17:00">
        <Text Halign="center" Valign="bottom" Hposition="0.0" Vposition="8.0">*leises Windrauschen*</Text>
      </Subtitle>
    </Font>
  </SubtitleList>
</SubtitleReel>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(13, 15, '*Lied: "Feeling Good" von Nina Simone*')->add(15, 17, '*leises Windrauschen*')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml6b()
    {
        $text = <<<X
<?xml version="1.0" encoding="UTF-8"?>
<dcst:SubtitleReel xmlns:dcst="http://www.smpte-ra.org/schemas/428-7/2010/DCST" xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <dcst:Id>urn:uuid:f7e1802b-9aea-4899-bc33-5a9d9011f0e9</dcst:Id>
  <dcst:ContentTitleText>title</dcst:ContentTitleText>
  <dcst:AnnotationText>This is a subtitle file</dcst:AnnotationText>
  <dcst:IssueDate>2024-02-04T20:17:48.000-00:00</dcst:IssueDate>
  <dcst:ReelNumber>1</dcst:ReelNumber>
  <dcst:Language>en</dcst:Language>
  <dcst:EditRate>25 1</dcst:EditRate>
  <dcst:TimeCodeRate>25</dcst:TimeCodeRate>
  <dcst:StartTime>00:00:00:00</dcst:StartTime>
  <dcst:LoadFont ID="theFontId">urn:uuid:3dec6dc0-39d0-498d-97d0-928d2eb78391</dcst:LoadFont>
  <dcst:SubtitleList>
    <dcst:Font ID="theFontId" Size="42" Weight="normal" Color="FFFFFFFF" Effect="border" EffectColor="FF000000">
      <dcst:Subtitle SpotNumber="1" FadeUpTime="00:00:00:00" FadeDownTime="00:00:00:00" TimeIn="00:00:30:04" TimeOut="00:00:33:21">
        <dcst:Text Vposition="8" Valign="bottom" Halign="center" Direction="ltr">L'extérieur est différent de l'intérieur.</dcst:Text>
      </dcst:Subtitle>
    </dcst:Font>
  </dcst:SubtitleList>
</dcst:SubtitleReel>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(30.154, 33.808, "L'extérieur est différent de l'intérieur.")->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml7()
    {
        $text = <<<X
<?xml version="1.0" encoding="utf-8" ?><transcript><text start="0.22" dur="3.66">Creating an online shop
is now easier than ever.</text><text start="3.88" dur="3.177">With a minimum investment of time and
money,</text></transcript>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0.22, 3.88, ['Creating an online shop', 'is now easier than ever.'])
            ->add(3.88, 7.057, ['With a minimum investment of time and', 'money,'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml7a()
    {
        $text = <<<X
<?xml version="1.0" encoding="utf-8" ?><transcript><text start="0.22">Creating an online shop
is now easier than ever.</text><text start="3.88">With a minimum investment of time and
money,</text></transcript>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0.22, 3.88, ['Creating an online shop', 'is now easier than ever.'])
            ->add(3.88, 4.88, ['With a minimum investment of time and', 'money,'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml8()
    {
        $text = <<<X
<?xml version="1.0"?>
<tt xmlns:vt="http://namespace.itunes.apple.com/itt/ttml-extension#vertical" xmlns:ttp="http://www.w3.org/ns/ttml#parameter" xmlns:ittp="http://www.w3.org/ns/ttml/profile/imsc1#parameter" xmlns:tt_feature="http://www.w3.org/ns/ttml/feature/" xmlns:ebutts="urn:ebu:tt:style" xmlns:tts="http://www.w3.org/ns/ttml#styling" xmlns:tt_extension="http://www.w3.org/ns/ttml/extension/" xmlns:tt_profile="http://www.w3.org/ns/ttml/profile/" xmlns:ttm="http://www.w3.org/ns/ttml#metadata" xmlns:ry="http://namespace.itunes.apple.com/itt/ttml-extension#ruby" xmlns:itts="http://www.w3.org/ns/ttml/profile/imsc1#styling" xmlns="http://www.w3.org/ns/ttml" xml:lang="cmn-Hant" ttp:dropMode="nonDrop" ttp:frameRate="30" ttp:frameRateMultiplier="1 1" ttp:timeBase="smpte" xmlns:tt="http://www.w3.org/ns/ttml" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <head>
    <styling>
      <style xml:id="normal" tts:color="white" tts:fontFamily="sansSerif" tts:fontSize="100%" tts:fontStyle="normal" tts:fontWeight="normal"/>
    </styling>
    <layout>
      <region xml:id="bottom" tts:displayAlign="after" tts:extent="100% 15%" tts:origin="0% 85%" tts:writingMode="lrtb"/>
    </layout>
  </head>
  <body tts:color="white" region="bottom" style="normal">
    <div>
      <p begin="00:00:12:02" end="00:00:13:06" region="bottom">Hello everyone</p>
      <p begin="00:00:13:06" end="00:00:15:04" region="bottom">welcome to my channel</p>
    </div>
  </body>
</tt>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(12.066, 13.2, 'Hello everyone')
            ->add(13.2, 15.133, 'welcome to my channel')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertFromXml9()
    {
        $text = <<<X
<?xml version="1.0" encoding="utf-8" ?>
<body>
<p t="9159" d="768" wp="2" ws="1"><s p="2">​</s><s>​</s><s p="3">​ ​チャン チャン​ ​</s><s p="2">​
​</s><s p="4">​ ​쟌 쟌​ ​</s><s p="2">​</s></p>
</body>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(9.159, 9.927, ['チャン チャン', '쟌 쟌'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testMultipleLines()
    {
        $text = <<<X
<?xml version="1.0" encoding="UTF-8"?>
<DCSubtitle Version="1.0">
  <SubtitleID>b74d4623-4dd7-486f-831f-24a453894c7d</SubtitleID>
  <MovieTitle>CONSEGUENZEen</MovieTitle>
  <ReelNumber>Unico</ReelNumber>
  <Language>English</Language>
  <Font Color="FFFFFFFF" Effect="border" EffectColor="FF000000" Size="42" Weight="normal">
    <Font Italic="yes">
      <Subtitle SpotNumber="2" TimeIn="00:00:01:000" TimeOut="00:00:02:000" FadeUpTime="0" FadeDownTime="0">
        <Text Direction="horizontal" HAlign="center" HPosition="0.0" VAlign="bottom" VPosition="14.0">The worst thing for a man</Text>
        <Text Direction="horizontal" HAlign="center" HPosition="0.0" VAlign="bottom" VPosition="6.0">who spends a lot of time alone</Text>
      </Subtitle>
    </Font>
  </Font>
</DCSubtitle>
X;
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, ['The worst thing for a man', 'who spends a lot of time alone'])->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }
}