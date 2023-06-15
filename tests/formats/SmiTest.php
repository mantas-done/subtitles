<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SmiConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class SmiTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesSmi()
    {
        $content = file_get_contents('./tests/files/smi.smi');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === SmiConverter::class);
    }

    public function testFileToInternalFormat()
    {
        $actual = Subtitles::loadFromFile('./tests/files/smi.smi', 'smi')->getInternalFormat();
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
        $actual = Subtitles::loadFromFile('./tests/files/smi_formatted.smi')->getInternalFormat();
        $expected = (new Subtitles())
            ->add(9.209, 12.312, '( clock ticking )')
            ->add(14.848, 17.35, [
                'MAN:',
                'When we think',
                'of E equals m c-squared,',
            ])
            ->add(17.35, 19.417, 'we have this vision of Einstein')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
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