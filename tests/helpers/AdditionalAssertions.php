<?php

trait AdditionalAssertions
{
    public function assertInternalFormatsEqual($expected, $actual, $allowable_error = 0.01)
    {
        foreach ($expected as $k => $block) {
            $this->assertTrue(abs(round($expected[$k]['start'], 2) - round($actual[$k]['start'], 2)) < $allowable_error, round($expected[$k]['start'], 3) . ' vs ' . round($actual[$k]['start'], 3));
            $this->assertTrue(abs(round($expected[$k]['end'], 2) - round($actual[$k]['end'], 2)) < $allowable_error,  round($expected[$k]['end'], 3) . ' vs ' . round($actual[$k]['end'], 3));

            foreach ($expected[$k]['lines'] as $line_k => $line) {
                $this->assertEquals($line, $actual[$k]['lines'][$line_k]);
            }
        }
    }
}
