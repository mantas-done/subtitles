<?php

namespace Formats;

use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class RtfTest extends TestCase
{
    use AdditionalAssertionsTrait;
    public function testParsesRtfFile()
    {
        $content = file_get_contents('./tests/files/rtf.rtf');
        $actual = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())->add(1, 2, 'word')->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testClientFileWithBackslashes()
    {
        $content = file_get_contents('./tests/files/rtf2.rtf');
        $actual = (new Subtitles())->loadFromString($content)->getInternalFormat();
        $expected = (new Subtitles())
            ->add(223, 229, 'Reflecting back on the nineteen forties, fifties and sixties')
            ->add(230, 240, 'During those times the elders back home were grieving,')
            ->getInternalFormat();
        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testInvalidRtf()
    {
        $this->expectExceptionMessage('RtfReader: File content is not valid');

        $string = base64_decode('e1xydGYxXGFuc2lcYW5zaWNwZzEyNTJcY29jb2FydGYyODY1Clxjb2NvYXRleHRzY2FsaW5nMFxjb2NvYXBsYXRmb3JtMHtcZm9udHRibFxmMFxmc3dpc3NcZmNoYXJzZXQwIEhlbHZldGljYTt9CntcY29sb3J0Ymw7XHJlZDI1NVxncmVlbjI1NVxibHVlMjU1O30Ke1wqXGV4cGFuZGVkY29sb3J0Ymw7O30KXHBhcGVydzExOTAwXHBhcGVyaDE2ODQwXG1hcmdsMTQ0MFxtYXJncjE0NDBcdmlld3cxMTUyMFx2aWV3aDg0MDBcdmlld2tpbmQwClxwYXJkXHR4NzIwXHR4MTQ0MFx0eDIxNjBcdHgyODgwXHR4MzYwMFx0eDQzMjBcdHg1MDQwXHR4NTc2MFx0eDY0ODBcdHg3MjAwXHR4NzkyMFx0eDg2NDBccGFyZGlybmF0dXJhbFxwYXJ0aWdodGVuZmFjdG9yMAoKXGYwXGZzMjQgXGNmMCB7e1xOZVhUR3JhcGhpYyBzZi11aS10ZXh0LTIgXHdpZHRoNjQwIFxoZWlnaHQ2NDAgXGFwcGxlYXR0YWNobWVudHBhZGRpbmcwIFxhcHBsZWVtYmVkdHlwZTAgXGFwcGxlYXFjCn2sfX0=');
        (new \Done\Subtitles\Subtitles())->loadFromString($string);
    }
}