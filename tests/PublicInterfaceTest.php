<?php

use PHPUnit\Framework\TestCase;
use Done\Subtitles\Subtitles;

class PublicInterfaceTest extends TestCase {

    public function testConvert()
    {
        $srt_path = './tests/files/srt_for_public_interface_test.srt';
        $temporary_srt_path = './tests/files/tmp/srt.srt';
        @unlink($temporary_srt_path);

        Subtitles::convert($srt_path, $temporary_srt_path);

        $this->assertFileExists($temporary_srt_path);
        unlink($temporary_srt_path);
    }

    public function testLoadFromFile()
    {
        $srt_path = './tests/files/srt_for_public_interface_test.srt';

        $subtitles = Subtitles::load($srt_path);

        $this->assertTrue(!empty($subtitles->getInternalFormat()));
    }

    public function testLoadFromString()
    {
        $string = "
1
00:02:17,440 --> 00:02:20,375
Senator, we're making
our final approach into Coruscant.
    ";
        $subtitles = Subtitles::load($string, 'srt');

        $this->assertTrue(!empty($subtitles->getInternalFormat()));
    }

    public function testLoadWithoutExtensionThrowsException()
    {
        $this->expectException(Exception::class);

        Subtitles::load("normal file\nnormal file");
    }

    public function testLoadFileThatDoesNotExist()
    {
        $this->expectException(Exception::class);

        Subtitles::load("some_random_name.srt");
    }

    public function testLoadFileWithNotSupportedExtension()
    {
        $this->expectException(Exception::class);

        Subtitles::load("subtitles.exe");
    }

    public function saveFile()
    {
        $srt_path = './tests/files/srt_for_public_interface_test.srt';
        $temporary_srt_path = './tests/files/tmp/srt.srt';
        @unlink($temporary_srt_path);

        Subtitles::load($srt_path)->save($temporary_srt_path);

        $this->assertFileExists($temporary_srt_path);

        unlink($temporary_srt_path);
    }

    public function testContent()
    {
        $srt_path = './tests/files/srt_for_public_interface_test.srt';

        $content = Subtitles::load($srt_path)->content('srt');

        $this->assertTrue(strlen($content) > 10); // 10 - just random number
    }

    public function testAdd()
    {
        $subtitles = new Subtitles();
        $subtitles->add(1, 2, 'Hello World');
        $actual_internal_format = $subtitles->getInternalFormat();
        $expected_internal_format = [[
            'start' => 1,
            'end' => 2,
            'lines' => ['Hello World'],
        ]];

        $this->assertTrue($expected_internal_format === $actual_internal_format);
    }

    // ----------------------------------------- remove() --------------------------------------------------------------
    public function testRemove()
    {
        $actual_internal_format = (new Subtitles())
            ->add(1, 3, 'Hello World')
            ->remove(0, 2)
            ->getInternalFormat();

        $this->assertTrue(empty($actual_internal_format));
    }

    public function testRemoveDoesNotRemoveIfTimesDoNotOverlapAtTheBeginning()
    {
        $actual_internal_format = (new Subtitles())
            ->add(1, 3, 'Hello World')
            ->remove(0, 1)
            ->getInternalFormat();

        $this->assertTrue(!empty($actual_internal_format));
    }

    public function testRemoveDoesNotRemoveIfTimesDoNotOverlapAtTheEnd()
    {
        $actual_internal_format = (new Subtitles())
            ->add(1, 3, 'Hello World')
            ->remove(3, 4)
            ->getInternalFormat();

        $this->assertTrue(!empty($actual_internal_format));
    }

    // ------------------------------------------------ time() ---------------------------------------------------------
    public function testAddTime()
    {
        $actual_internal_format = (new Subtitles())
            ->add(1, 3, 'Hello World')
            ->time(1)
            ->getInternalFormat();
        $expected_internal_format = [[
            'start' => 2,
            'end' => 4,
            'lines' => ['Hello World'],
        ]];

        $this->assertTrue($expected_internal_format === $actual_internal_format);
    }

    public function testSubtractTime()
    {
        $actual_internal_format = (new Subtitles())
            ->add(1, 3, 'Hello World')
            ->time(-1)
            ->getInternalFormat();
        $expected_internal_format = [[
            'start' => 0,
            'end' => 2,
            'lines' => ['Hello World'],
        ]];

        $this->assertTrue($expected_internal_format === $actual_internal_format);
    }

}