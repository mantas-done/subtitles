<?php

namespace Helpers;

use Done\Subtitles\Subtitles;

trait AdditionalAssertionsTrait
{
    public function assertInternalFormatsEqual($expected, $actual, $allowable_error = 0.01)
    {
        $this->assertEquals(count($expected), count($actual), 'Different counts of elements ' . count($expected) . ' vs ' . count($actual));

        foreach ($expected as $k => $block) {
            $this->assertTrue(abs(round($expected[$k]['start'], 2) - round($actual[$k]['start'], 2)) < $allowable_error, round($expected[$k]['start'], 3) . ' vs ' . round($actual[$k]['start'], 3));
            $this->assertTrue(abs(round($expected[$k]['end'], 2) - round($actual[$k]['end'], 2)) < $allowable_error,  round($expected[$k]['end'], 3) . ' vs ' . round($actual[$k]['end'], 3));

            $this->assertEquals(count($expected[$k]['lines']), count($actual[$k]['lines']), 'Line count is different');
            foreach ($expected[$k]['lines'] as $line_k => $line) {
                $this->assertEquals($line, $actual[$k]['lines'][$line_k]);
            }
        }
    }

    public function assertFileEqualsIgnoringLineEndings($expected_file_path, $actual_file_path)
    {
        $expected_file_string = file_get_contents($expected_file_path);
        $tmp_dfxp_string = file_get_contents($actual_file_path);
        $expected_file_string = str_replace("\r", "", $expected_file_string);
        $tmp_dfxp_string = str_replace("\r", "", $tmp_dfxp_string);
        $this->assertEquals($expected_file_string, $tmp_dfxp_string);
    }

    public function defaultSubtitles()
    {
        return (new Subtitles())
            ->add(137.4, 140.4, ['Senator, we\'re making', 'our final approach into Coruscant.'])
            ->add(3740.5, 3742.5, ['Very good, Lieutenant.']);
    }
}
