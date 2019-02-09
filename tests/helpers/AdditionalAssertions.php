<?php

trait AdditionalAssertions
{
    public function assertInternalFormatsEqual($expected, $actual)
    {
        foreach ($expected as $k => $block) {
            $this->assertTrue(round($expected[$k]['start'], 2) === round($actual[$k]['start'], 2), round($expected[$k]['start'], 2) . ' vs ' . round($actual[$k]['start'], 2));
            $this->assertTrue(round($expected[$k]['end'], 2) === round($actual[$k]['end'], 2),  round($expected[$k]['end'], 2) . ' vs ' . round($actual[$k]['end'], 2));

            foreach ($expected[$k]['lines'] as $line_k => $line) {
                $this->assertEquals($line, $actual[$k]['lines'][$line_k]);
            }
        }
    }
}
