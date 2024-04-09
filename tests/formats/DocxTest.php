<?php

namespace Formats;

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class DocxTest extends TestCase
{

    use AdditionalAssertionsTrait;

    public function testParsesDocxFile()
    {
        $content = file_get_contents('./tests/files/docx.docx');
        $actual = Subtitles::loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(137.4, 140.4, ["Senator, we're making", 'our final approach into Coruscant.'])
            ->add(3740.5, 3742.5, ['Very good, Lieutenant.'])
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }
}