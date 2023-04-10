<?php

use Circlical\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;

class SbvTest extends TestCase
{

    use AdditionalAssertions;

    public function testFileToInternalFormat()
    {
        $fileContent = <<<TEXT
0:05:40.000,0:05:46.000
Don’t think that you can just ignore them
because they’re not your children or relatives.

0:05:46.000,0:05:51.000
Because every child in our society is
a part of that society
TEXT;
        $generatedFormat = Subtitles::load($fileContent, 'sbv')->getInternalFormat();
        $expectedFormat = [
            [
                'start' => 340,
                'end' => 346,
                'lines' => ['Don’t think that you can just ignore them', 'because they’re not your children or relatives.'],
            ],
            [
                'start' => 346,
                'end' => 351,
                'lines' => ['Because every child in our society is', 'a part of that society'],
            ],
        ];

        $this->assertInternalFormatsEqual($expectedFormat, $generatedFormat);
    }

    public function testConvertToFile()
    {
        $internalFormat = [
            [
                'start' => 340,
                'end' => 346,
                'lines' => ['Don’t think that you can just ignore them', 'because they’re not your children or relatives.'],
            ],
            [
                'start' => 346,
                'end' => 351,
                'lines' => ['Because every child in our society is', 'a part of that society'],
            ],
        ];

        $fileContent = <<<TEXT
0:05:40.000,0:05:46.000
Don’t think that you can just ignore them
because they’re not your children or relatives.

0:05:46.000,0:05:51.000
Because every child in our society is
a part of that society
TEXT;

        $generatedFormat = (new Subtitles())->setInternalFormat($internalFormat)->content('sbv');
        $this->assertEquals($fileContent, $generatedFormat);
    }
}