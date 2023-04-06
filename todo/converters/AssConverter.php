<?php

declare(strict_types=1);


namespace converters;

use Done\Subtitles\Providers\ConverterInterface;

class AssConverter implements ConverterInterface
{
    public function parseSubtitles(string $fileContent): array
    {
        preg_match_all('/Dialogue: \d+,([^,]*),([^,]*),[^,]*,[^,]*,[^,]*,[^,]*,[^,]*,[^,]*,(.*)/', $fileContent, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $block) {
            $internalFormat[] = [
                'start' => static::assTimeToInternal($block[1]),
                'end' => static::assTimeToInternal($block[2]),
                'lines' => explode('\N', $block[3]),
            ];
        }

        return $internalFormat;
    }

    public function toSubtitles(array $internalFormat): string
    {
        $fileContent = '[Script Info]
; This is an Advanced Sub Station Alpha v4+ script.
Title: subtitles
ScriptType: v4.00+
Collisions: Normal
PlayDepth: 0

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, 
StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,Arial,20,&H00FFFFFF,&H0300FFFF,&H00000000,&H02000000,0,0,0,0,100,100,0,0,1,2,1,2,10,10,10,1

[Events]
Format: Layer, Start, End, Style, Actor, MarginL, MarginR, MarginV, Effect, Text
';

        foreach ($internalFormat as $k => $block) {
            $start = static::internalTimeToSrt($block['start']);
            $end = static::internalTimeToSrt($block['end']);
            $lines = implode('\N', $block['lines']);

            $fileContent .= "Dialogue: 0,{$start},{$end},Default,,0,0,0,,{$lines}\r\n";
        }

        $fileContent = trim($fileContent);

        return $fileContent;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     */
    protected static function assTimeToInternal(string $srtTime): float
    {
        $parsed = date_parse("1970-01-01 $srtTime UTC");

        return $parsed['hour'] * 3600 + $parsed['minute'] * 60 + $parsed['second'] + $parsed['fraction'];
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17,440
     */
    protected static function internalTimeToSrt(string $internalTime): string
    {
        $parts = explode('.', $internalTime);
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 2) : 0;

        return gmdate("G:i:s", (int) floor($whole)) . '.' . str_pad($decimal, 2, '0', STR_PAD_RIGHT);
    }
}
