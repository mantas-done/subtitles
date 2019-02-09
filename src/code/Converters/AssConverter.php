<?php namespace Done\Subtitles;

class AssConverter implements ConverterContract {

    public function fileContentToInternalFormat($file_content)
    {
        preg_match_all('/0,([^,]*),([^,]*),Default,,0,0,0,,(.*)/', $file_content, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $block) {
            $internal_format[] = [
                'start' => static::assTimeToInternal($block[1]),
                'end' => static::assTimeToInternal($block[2]),
                'lines' => explode('\N', $block[3]),
            ];
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format)
    {
        $file_content = '[Script Info]
; This is an Advanced Sub Station Alpha v4+ script.
Title: subtitles
ScriptType: v4.00+
Collisions: Normal
PlayDepth: 0

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,Arial,20,&H00FFFFFF,&H0300FFFF,&H00000000,&H02000000,0,0,0,0,100,100,0,0,1,2,1,2,10,10,10,1

[Events]
Format: Layer, Start, End, Style, Actor, MarginL, MarginR, MarginV, Effect, Text
';

        foreach ($internal_format as $k => $block) {
            $start = static::internalTimeToSrt($block['start']);
            $end = static::internalTimeToSrt($block['end']);
            $lines = implode('\N', $block['lines']);

            $file_content .= "Dialogue: 0,{$start},{$end},Default,,0,0,0,,{$lines}\r\n";
        }

        $file_content = trim($file_content);

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     *
     * @param $srt_time
     *
     * @return float
     */
    protected static function assTimeToInternal($srt_time)
    {
        $parsed = date_parse("1970-01-01 $srt_time UTC");
        $time = $parsed['hour'] * 3600 + $parsed['minute'] * 60 + $parsed['second'] + $parsed['fraction'];

        return $time;
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17,440
     *
     * @param float $internal_time
     *
     * @return string
     */
    protected static function internalTimeToSrt($internal_time)
    {
        $parts = explode('.', $internal_time);
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 2) : 0;

        $srt_time = gmdate("G:i:s", floor($whole)) . '.' . str_pad($decimal, 2, '0', STR_PAD_RIGHT);

        return $srt_time;
    }
}
