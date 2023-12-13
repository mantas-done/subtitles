<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\EbuStlConverter;
use Done\Subtitles\Code\Converters\VttConverter;
use Done\Subtitles\Code\Formats\Vtt;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class EbuStlTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testParsesEbuStl()
    {
        $stl_path = './tests/files/ebu_stl_iso6937.stl';
        $actual = Subtitles::loadFromFile($stl_path)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(3599.967, 3603.267, ['Les réalisateurs de ce film ont passé', 'plus de deux ans sur la route'])
            ->add(3603.367, 3605.633, 'avec Ozzy Osbourne.')
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testTextConversion()
    {
        $actual = EbuStlConverter::iso6937ToUtf8("\xA439");
        $this->assertEquals('$39', $actual);
    }
}