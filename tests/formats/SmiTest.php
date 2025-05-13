<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SmiConverter;
use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;
use PHPUnit\Framework\TestCase;

class SmiTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesSmi()
    {
        $content = file_get_contents('./tests/files/smi.smi');
        $converter = Helpers::getConverterByFileContent((new Subtitles())->getFormats(), $content, $content);
        $this->assertTrue(get_class($converter) === SmiConverter::class);
    }

    public function testFileToInternalFormat()
    {
        $actual = (new Subtitles())->loadFromFile('./tests/files/smi.smi', 'smi')->getInternalFormat();
        $expected = self::generatedSubtitles()->getInternalFormat();
            $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testConvertToFile()
    {
        $actual_file_content = self::generatedSubtitles()->content('smi');
        $this->assertStringEqualsStringIgnoringLineEndings(self::fileContent(), $actual_file_content);
    }

    public function testFormatted()
    {
        $actual = (new Subtitles())->loadFromFile('./tests/files/smi_formatted.smi')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(9.209, 12.312, '( clock ticking )')
            ->add(14.848, 17.35, [
                'MAN:',
                'When we think',
                'of E equals m c-squared,',
            ])
            ->add(17.35, 18.35, 'we have this vision of Einstein')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testEscapes()
    {
        $actual = (new Subtitles())->add(0,  1, '<script>test</script>')->content('smi');
        $this->assertFalse(Helpers::strContains($actual, '<script>'));

    }

    public function testNegativeTime()
    {
        $actual = (new Subtitles())->loadFromString('
<SAMI>
<BODY>
<SYNC Start=-100><P Class=ENUSCC>a</P></SYNC>
<SYNC Start=140400><P Class=ENUSCC>&nbsp;</P></SYNC>
<SYNC Start=3740500><P Class=ENUSCC>b</P></SYNC>
<SYNC Start=3742500><P Class=ENUSCC>&nbsp;</P></SYNC>
</BODY>
</SAMI>
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 140.4, 'a')
            ->add(3740.5, 3742.5, 'b')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testMsNearTimestamp()
    {
        $actual = (new Subtitles())->loadFromString('
<SAMI>
<BODY>
<SYNC Start="1000ms"><P Class=ENUSCC>a</P></SYNC>
</BODY>
</SAMI>
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(1, 2, 'a')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testNoP()
    {
        $actual = (new Subtitles())->loadFromString('
<SAMI>
<BODY>
<SYNC Start=0>a</SYNC>
<SYNC Start=1000>&nbsp;</SYNC>
</BODY>
</SAMI>
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 1, 'a')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testClientFile1()
    {
        $actual = (new Subtitles())->loadFromString("
<SAMI>
	<BODY>
		<SYNC START=\"9560\">
\t\t\t<P CLASS=\".ITCC\"><BR><BR>La sicurezza<BR> un argomento importantissimo.</P>
		</SYNC>
		<SYNC START=\"12840\">
				<P CLASS=\".ITCC\">&nbsp</P>
		</SYNC>
	</BODY>
</SAMI>
        ")->getInternalFormat();
        $expected = (new Subtitles())
            ->add(9.560, 12.840, ['La sicurezza', 'un argomento importantissimo.'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testClientFile2()
    {
        $actual = (new Subtitles())->loadFromString('<SAMI>
  <HEAD>
    <STYLE TYPE="text/css">
      <!--
        P {
          font-size: 12pt;
          font-family: Verdana;
          font-weight: normal;
          font-style: normal;
          color: #FFFFFF;
          background: #000000;
          text-align: center;
        }
        .Captions { Name: Captions; lang: EN_US_CC; SAMI_Type: CC;}
      -->
    </STYLE>
  </HEAD>
  <BODY>
    <SYNC Start="141516">
      <P Class="Captions">test</P>
    </SYNC>
  </BODY>
</SAMI>
')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(141.516, 142.516, 'test')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testClientFile3()
    {
        $actual = (new Subtitles())->loadFromString('<SAMI>
<HEAD>
<TITLE>금토드라마(열혈사제)-35회(19년04월13일(토))</TITLE>
<HEAD>
<BODY>
<SYNC START=144700>
<SYNC START=145308><FONT COLOR="FFFF00">-야, 처리해. </FONT>
<SYNC START=145994>-야, 이중권.
</BODY>
</SAMI>
')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(145.308, 145.994, '-야, 처리해.')
            ->add(145.994, 146.994, '-야, 이중권.')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testNonEnglishWords()
    {
        $actual = (new Subtitles())->loadFromString('
<SAMI>
<BODY>
<SYNC Start=0><P Class=ENUSCC>늘</P></SYNC>
<SYNC Start=140400><P Class=ENUSCC>&nbsp;</P></SYNC>
</BODY>
</SAMI>
        ')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(0, 140.4, '늘')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testNoTextDoesntThrowPhpException()
    {
        $this->expectException(UserException::class);

        $actual = (new Subtitles())->loadFromString('
<SAMI>
<HEAD>
<STYLE TYPE="text/css">
<!--  Generated by KMPlayer
.[Local] Masked Avengers.srt	
-->
</STYLE>
</HEAD>
<BODY>
<SYNC Start=20000><P Class=[Local] Masked Avengers.srt	>&nbsp;
<SYNC Start=20000><P Class=[Local] Masked Avengers.srt	>&nbsp;
<SYNC Start=20000><P Class=[Local] Masked Avengers.srt	>&nbsp;
</BODY>
</SAMI>
        ')->getInternalFormat();
    }

    // ---------------------------------- private ----------------------------------------------------------------------

    private static function fileContent()
    {
        return file_get_contents('./tests/files/smi.smi');
    }

    private static function generatedSubtitles()
    {
        return $expected_internal_format = (new Subtitles())
            ->add(137.4, 140.4, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.5, 3742.5, ['Very good, Lieutenant.']);
    }

}