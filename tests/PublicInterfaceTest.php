<?php

namespace Tests;

use Done\Subtitles\Code\Converters\VttConverter;
use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use Helpers\AdditionalAssertionsTrait;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\FakeDocxConverter;

class PublicInterfaceTest extends TestCase
{
    use AdditionalAssertionsTrait;

    public function testConvert()
    {
        $srt_path = './tests/files/srt_for_public_interface_test.srt';
        $temporary_srt_path = './tests/files/tmp/srt.srt';
        @unlink($temporary_srt_path);

        (new Subtitles())->convert($srt_path, $temporary_srt_path);

        $this->assertFileExists($temporary_srt_path);
        unlink($temporary_srt_path);
    }

    public function testConvertUsingThirdParameter()
    {
        $srt_path = './tests/files/srt_for_public_interface_test.srt';
        $temporary_srt_path = './tests/files/tmp/file.no_extension';
        @unlink($temporary_srt_path);

        (new Subtitles())->convert($srt_path, $temporary_srt_path, ['output_format' => 'vtt']);
        $converter = (new Subtitles())->getFormat(file_get_contents($temporary_srt_path))['class'];
        unlink($temporary_srt_path);

        $this->assertEquals(VttConverter::class, $converter);
    }

    public function testLoadFromFile()
    {
        $srt_path = './tests/files/srt_for_public_interface_test.srt';

        $subtitles = (new Subtitles())->loadFromFile($srt_path);

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
        $subtitles = (new Subtitles())->loadFromString($string);

        $this->assertTrue(!empty($subtitles->getInternalFormat()));
    }

    public function testLoadWithoutExtensionThrowsException()
    {
        $this->expectException(\RuntimeException::class);

        (new Subtitles())->loadFromFile("normal file\nnormal file");
    }

    public function testLoadFileThatDoesNotExist()
    {
        $this->expectException(\RuntimeException::class);

        (new Subtitles())->loadFromFile("some_random_name.srt");
    }

    public function testLoadFileWithNotSupportedExtension()
    {
        $this->expectException(\RuntimeException::class);

        (new Subtitles())->loadFromFile("subtitles.exe");
    }

    public function saveFile()
    {
        $srt_path = './tests/files/srt_for_public_interface_test.srt';
        $temporary_srt_path = './tests/files/tmp/srt.srt';
        @unlink($temporary_srt_path);

        (new Subtitles())->loadFromFile($srt_path)->save($temporary_srt_path);

        $this->assertFileExists($temporary_srt_path);

        unlink($temporary_srt_path);
    }

    public function testContent()
    {
        $srt_path = './tests/files/srt_for_public_interface_test.srt';

        $content = (new Subtitles())->loadFromFile($srt_path)->content('srt');

        $this->assertTrue(strlen($content) > 10); // 10 - just random number
    }

    public function testNonExistentFormatThrowsError()
    {
        $this->expectException(\Exception::class);

        $srt_path = './tests/files/srt_for_public_interface_test.srt';
        (new Subtitles())->loadFromFile($srt_path)->content('exe');
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

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testAddOrdersSubtitlesByTime()
    {
        $expected_internal_format = [[
                'start' => 0,
                'end' => 5,
                'lines' => ['text 1'],
            ], [
                'start' => 10,
                'end' => 15,
                'lines' => ['text 2'],
            ]];

        $subtitles = new Subtitles();
        $subtitles->add(10, 15, 'text 2');
        $subtitles->add(0, 5, 'text 1');
        $actual_internal_format = $subtitles->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testParsesWithoutLineText()
    {
        $text = "
1
00:00:00,000 --> 00:00:23,000


2
00:00:23,000 --> 00:00:25,000
text
";
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(23, 25, 'text')->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
    }

    public function testParsesNoNumbers()
    {
        $text = "
00:00:00,000 --> 00:00:08,000
text
";
        $actual = (new Subtitles())->loadFromString($text)->getInternalFormat();
        $expected = (new Subtitles())->add(0, 8, 'text')->getInternalFormat();

        $this->assertInternalFormatsEqual($expected, $actual);
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
            ->shiftTime(1)
            ->getInternalFormat();
        $expected_internal_format = [[
            'start' => 2,
            'end' => 4,
            'lines' => ['Hello World'],
        ]];

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testSubtractTime()
    {
        $actual_internal_format = (new Subtitles())
            ->add(1, 3, 'Hello World')
            ->shiftTime(-1)
            ->getInternalFormat();
        $expected_internal_format = [[
            'start' => 0,
            'end' => 2,
            'lines' => ['Hello World'],
        ]];

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testFromTillTime()
    {
        $actual_internal_format = (new Subtitles())
            ->add(1, 3, 'a')
            ->shiftTime(1, 1, 3)
            ->getInternalFormat();
        $expected_internal_format = [[
            'start' => 2,
            'end' => 4,
            'lines' => ['a'],
        ]];

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testFromTillTimeWhenNotInRange()
    {
        $actual_internal_format1 = (new Subtitles())
            ->add(1, 3, 'a')
            ->shiftTime(1, 0, 0.5)
            ->getInternalFormat();
        $actual_internal_format2 = (new Subtitles())
            ->add(1, 3, 'a')
            ->shiftTime(1, 4, 5)
            ->getInternalFormat();
        $expected_internal_format = [[
            'start' => 1,
            'end' => 3,
            'lines' => ['a'],
        ]];

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format1);
        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format2);
    }

    public function testFromTillTimeOverlappingStart()
    {
        $actual_internal_format = (new Subtitles())
            ->add(1, 3, 'a')
            ->shiftTime(1, 0, 1)
            ->getInternalFormat();
        $expected_internal_format = [[
            'start' => 2,
            'end' => 4,
            'lines' => ['a'],
        ]];

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testSiftTimeGradually()
    {
        $actual_internal_format = (new Subtitles())
            ->add(0, 2, 'a')
            ->add(2, 4, 'a')
            ->add(4, 6, 'a')
            ->shiftTimeGradually(3)
            ->getInternalFormat();
        $expected_internal_format = (new Subtitles())
            ->add(0, 3, 'a')
            ->add(3, 6, 'a')
            ->add(6, 9, 'a')
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testSiftTimeGraduallyWithFromAndTill()
    {
        $actual_internal_format = (new Subtitles())
            ->add(0, 2, 'a')
            ->add(2, 4, 'a')
            ->add(4, 6, 'a')
            ->add(6, 8, 'a')
            ->add(8, 10, 'a')
            ->shiftTimeGradually(3, 2, 8)
            ->getInternalFormat();
        $expected_internal_format = (new Subtitles())
            ->add(0, 2, 'a')
            ->add(2, 5, 'a')
            ->add(5, 8, 'a')
            ->add(8, 11, 'a')
            ->add(8, 10, 'a')
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    // ------------------------------------ shiftTimeGradually ---------------------------------------------------------

    public function testTrim()
    {
        $actual_internal_format = (new Subtitles())
            ->add(0, 2, 'a')
            ->add(2, 4, 'b')
            ->add(4, 6, 'c')
            ->add(6, 8, 'd')
            ->add(8, 10, 'e')
            ->trim(4, 8)
            ->getInternalFormat();

        $expected_internal_format = (new Subtitles())
             ->add(4, 6, 'c')
            ->add(6, 8, 'd')
            ->add(8, 10, 'e')
            ->getInternalFormat();

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }

    public function testGetsFormat()
    {
        $srt_path = './tests/files/srt.srt';
        $format = (new Subtitles())->getFormat(file_get_contents($srt_path));
        $this->assertEquals('srt', $format['extension']);
    }

    public function testNotASubtitleFormatThrowsException()
    {
        $this->expectException(UserException::class);

        $srt_path = './tests/files/slick.bin';
        (new Subtitles())->getFormat(file_get_contents($srt_path));
    }

    public function testRegisterConverter()
    {
        $subtitles = new Subtitles();

        // add new converter
        $initial_format_count = count($subtitles->getFormats());
        $subtitles->registerConverter(FakeDocxConverter::class, 'docx_fake', 'docx2', 'Fake docx converter');
        $after_addition_count = count($subtitles->getFormats());
        $this->assertEquals($initial_format_count + 1, $after_addition_count);

        // replacing existing converter
        $initial_format_count = count($subtitles->getFormats());
        $subtitles->registerConverter(FakeDocxConverter::class, 'docx_fake', 'docx2', 'Fake docx converter');
        $after_replacement_count = count($subtitles->getFormats());
        $this->assertEquals($initial_format_count, $after_replacement_count); // adds new

        // from format
        $actual_internal_format = $subtitles->loadFromString('fake_docx')->getInternalFormat();
        $expected_internal_format = [['start' => 22, 'end' => 33, 'lines' => ['fake_docx']]];
        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);

        // to format
        $actual = $subtitles->add(1, 2, 'a')->content('docx_fake');
        $this->assertEquals('a', $actual);
    }
}
