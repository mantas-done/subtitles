<?php

use Done\Subtitles\Subtitles;

class SubSubtitle extends SubtitleCase {

    public function testFileToInternalFormat()
    {
        $actual_internal_format = Subtitles::load(self::fileContent(), 'sub')->getInternalFormat();

        $this->assertInternalFormatsEqual(self::internalFormat(), $actual_internal_format);
    }



    public function testConvertToFile()
    {
        $actual_file_content = (new Subtitles())->setInternalFormat(self::internalFormat())->content('sub');

        $this->assertEquals(self::fileContent(), $actual_file_content);
    }

    // @TODO test time above 1 hour

    // ---------------------------------- private ----------------------------------------------------------------------

    private static function fileContent()
    {
        $content = <<< TEXT
00:05:35.00,00:05:38.00
Hello guys... please sit down...

00:05:42.00,00:05:50.00
M. Franklin,[br]are you crazy?
TEXT;
        $content = str_replace("\r", '', $content);

        return $content;

    }

    private static function internalFormat()
    {
        return (new Subtitles())
            ->add(335, 338, ['Hello guys... please sit down...'])
            ->add(342, 350, ['M. Franklin,', 'are you crazy?'])
            ->getInternalFormat();
    }

}
