<?php

use Done\Subtitles\Subtitles;

trait TextBasedFileTrait {

    public function testFileToInternalFormat()
    {
        $actual_internal_format = Subtitles::load(self::fileContent(), $this->format)->getInternalFormat();

        $this->assertInternalFormatsEqual(self::generatedSubtitles()->getInternalFormat(), $actual_internal_format);
    }

    public function testConvertToFile()
    {
        $actual_file_content = self::generatedSubtitles()->content($this->format);

        $this->assertEquals(self::fileContent(), $actual_file_content);
    }

}