<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SccConverter;
use Done\Subtitles\Code\Helpers;
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

    public function testConvertsToScc()
    {
        $expected = file_get_contents('./tests/files/scc.scc');
        $actual = $this->defaultSubtitles()->content('scc');
        $this->assertStringEqualsStringIgnoringLineEndings($expected, $actual);
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

}