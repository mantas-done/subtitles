<?php

namespace Done\Subtitles\Code\Formats;

use Done\Subtitles\Subtitles;

class Vtt extends Subtitles
{
    public function add($start, $end, $text, $settings = [])
    {
        $lines = is_array($text) ? $text : [$text];
        $new_lines = [];
        $new_speakers = [];
        foreach ($lines as $speaker => $line) {
            $new_lines[] = $line;
            $new_speakers[] = is_numeric($speaker) ? null : $speaker;
        }

        $internal_format = [
            'start' => $start,
            'end' => $end,
            'lines' => $new_lines,
        ];

        if (isset($settings['settings'])) {
            $internal_format['vtt']['settings'] = $settings['settings']; // styles
        }

        $is_speaker = false;
        foreach ($new_speakers as $speaker) {
            if ($speaker !== null) {
                $is_speaker = true;
            }
        }
        if ($is_speaker) {
            $internal_format['vtt']['speakers'] = $new_speakers;
        }

        $this->internal_format[] = $internal_format;
        $this->sortInternalFormat();

        return $this;
    }
}
