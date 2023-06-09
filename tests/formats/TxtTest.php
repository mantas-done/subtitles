<?php

namespace Tests\Formats;

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\AdditionalAssertionsTrait;

class TxtTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testFileToInternalFormat()
    {
        $actual_internal_format = Subtitles::load(self::fileContent(), 'txt')->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $actual_internal_format);
    }

    public function testConvertToFile()
    {
        $generated_subtitles = (new Subtitles())
            ->add(0, 1, ['Senator, we\'re making our', 'final approach into Coruscant.'])
            ->add(1, 2, ['Very good, Lieutenant.']);

        $actual_file_content = $generated_subtitles->content('txt');

        $this->assertStringEqualsStringIgnoringLineEndings(self::fileContent(), $actual_file_content);
    }

    // ---------------------------------- private ----------------------------------------------------------------------

    private static function fileContent()
    {
        $content = <<< TEXT
Senator, we're making our final approach into Coruscant.
Very good, Lieutenant.
TEXT;

        return $content;
    }

    private static function generatedSubtitles()
    {
        return (new Subtitles())
            ->add(0, 1, ['Senator, we\'re making our final approach into Coruscant.'])
            ->add(1, 2, ['Very good, Lieutenant.']);
    }

}