<?php

abstract class SubtitleCase extends \PHPUnit\Framework\TestCase
{
    public function assertInternalFormatsEqual($expected, $actual)
    {
        foreach ($expected as $k => $block) {
            $this->assertTrue(round($expected[$k]['start'], 3) === round($actual[$k]['start'], 3));
            $this->assertTrue(round($expected[$k]['end'], 3) === round($actual[$k]['end'], 3));
            $this->assertTrue($expected[$k]['lines'] === $actual[$k]['lines']);
        }
    }
}
