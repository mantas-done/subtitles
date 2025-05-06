<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\EbuStlReader;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;
use PHPUnit\Framework\TestCase;

class EbuStlTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testParsesEbuStl()
    {
        $stl_path = './tests/files/ebu_stl_iso6937.stl';
        $actual = (new Subtitles())->loadFromFile($stl_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(3599.967, 3603.267, ['Les réalisateurs de ce film ont passé', 'plus de deux ans sur la route'])
            ->add(3603.367, 3605.633, 'avec Ozzy Osbourne.')
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testTextConversion()
    {
        $actual = EbuStlReader::iso6937ToUtf8("\xA439");
        $this->assertEquals('$39', $actual);
    }
}