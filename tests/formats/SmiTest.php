<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SmiConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class SmiTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesSrt()
    {
        $content = file_get_contents('./tests/files/smi.smi');
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === SmiConverter::class);
    }

    public function testFileToInternalFormat()
    {
        $actual_internal_format = Subtitles::load('./tests/files/smi.smi', 'smi')->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $actual_internal_format);
    }

    public function testConvertToFile()
    {
        $actual_file_content = self::generatedSubtitles()->content('smi');
        $this->assertStringEqualsStringIgnoringLineEndings(self::fileContent(), $actual_file_content);
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