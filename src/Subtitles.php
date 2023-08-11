<?php

namespace Done\Subtitles;

use Done\Subtitles\Code\Converters\AssConverter;
use Done\Subtitles\Code\Converters\CsvConverter;
use Done\Subtitles\Code\Converters\DfxpConverter;
use Done\Subtitles\Code\Converters\LrcConverter;
use Done\Subtitles\Code\Converters\SbvConverter;
use Done\Subtitles\Code\Converters\SccConverter;
use Done\Subtitles\Code\Converters\SmiConverter;
use Done\Subtitles\Code\Converters\SrtConverter;
use Done\Subtitles\Code\Converters\StlConverter;
use Done\Subtitles\Code\Converters\SubMicroDvdConverter;
use Done\Subtitles\Code\Converters\SubViewerConverter;
use Done\Subtitles\Code\Converters\TtmlConverter;
use Done\Subtitles\Code\Converters\TxtConverter;
use Done\Subtitles\Code\Converters\TxtQuickTimeConverter;
use Done\Subtitles\Code\Converters\VttConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;

class Subtitles
{
    protected $input;

    protected $internal_format; // data in internal format (when file is converted)

    protected $converter;
    protected $output;

    public static $formats = [
        ['extension' => 'ass',  'format' => 'ass',              'name' => 'Advanced Sub Station Alpha', 'class' => AssConverter::class],
        ['extension' => 'ssa',  'format' => 'ass',              'name' => 'Advanced Sub Station Alpha', 'class' => AssConverter::class],
        ['extension' => 'dfxp', 'format' => 'dfxp',             'name' => 'Netflix Timed Text',         'class' => DfxpConverter::class],
        ['extension' => 'sbv',  'format' => 'sbv',              'name' => 'YouTube',                    'class' => SbvConverter::class],
        ['extension' => 'srt',  'format' => 'srt',              'name' => 'SubRip',                     'class' => SrtConverter::class],
        ['extension' => 'stl',  'format' => 'stl',              'name' => 'Spruce Subtitle File',       'class' => StlConverter::class],
        ['extension' => 'sub',  'format' => 'sub_microdvd',     'name' => 'MicroDVD',                   'class' => SubMicroDvdConverter::class],
        ['extension' => 'sub',  'format' => 'sub_subviewer',    'name' => 'SubViewer2.0',               'class' => SubViewerConverter::class],
        ['extension' => 'ttml', 'format' => 'ttml',             'name' => 'TimedText 1.0',              'class' => TtmlConverter::class],
        ['extension' => 'xml',  'format' => 'ttml',             'name' => 'TimedText 1.0',              'class' => TtmlConverter::class],
        ['extension' => 'smi',  'format' => 'smi',              'name' => 'SAMI',                       'class' => SmiConverter::class],
        ['extension' => 'txt',  'format' => 'txt_quicktime',    'name' => 'Quick Time Text',            'class' => TxtQuickTimeConverter::class],
        ['extension' => 'vtt',  'format' => 'vtt',              'name' => 'WebVTT',                     'class' => VttConverter::class],
        ['extension' => 'scc',  'format' => 'scc',              'name' => 'Scenarist',                  'class' => SccConverter::class],
        ['extension' => 'lrc',  'format' => 'lrc',              'name' => 'LyRiCs',                     'class' => LrcConverter::class],
        ['extension' => 'csv',  'format' => 'csv',              'name' => 'Coma Separated Values',      'class' => CsvConverter::class], // must be last from bottom
        ['extension' => 'txt',  'format' => 'txt',              'name' => 'Plaintext',                  'class' => TxtConverter::class], // must be the last one
    ];

    public static function convert($from_file_path, $to_file_path, $to_format = null)
    {
        static::loadFromFile($from_file_path)->save($to_file_path, $to_format);
    }

    public function save($path, $format = null)
    {
        $file_extension = Helpers::fileExtension($path);
        if ($format === null) {
            $format = $file_extension;
        }
        $content = $this->content($format);

        file_put_contents($path, $content);

        return $this;
    }

    public function add($start, $end, $text, $settings = [])
    {
        // @TODO validation
        // @TODO check subtitles to not overlap
        $internal_format = [
            'start' => $start,
            'end' => $end,
            'lines' => is_array($text) ? $text : [$text],
        ];

        if (isset($settings['vtt_cue_settings']) && $settings['vtt_cue_settings']) {
            $internal_format['vtt_cue_settings'] = $settings['vtt_cue_settings'];
        }

        $this->internal_format[] = $internal_format;
        $this->sortInternalFormat();

        return $this;
    }

    public function trim($startTime, $endTime)
    {
        $this->remove('0', $startTime);
        $this->remove($endTime, $this->maxTime());

        return $this;
    }

    public function remove($from, $till)
    {
        foreach ($this->internal_format as $k => $block) {
            if ($this->shouldBlockBeRemoved($block, $from, $till)) {
                unset($this->internal_format[$k]);
            }
        }

        $this->internal_format = array_values($this->internal_format); // reorder keys

        return $this;
    }

    public function shiftTime($seconds, $from = 0, $till = null)
    {
        foreach ($this->internal_format as &$block) {
            if (!Helpers::shouldBlockTimeBeShifted($from, $till, $block['start'], $block['end'])) {
                continue;
            }

            $block['start'] += $seconds;
            $block['end'] += $seconds;
        }
        unset($block);

        $this->sortInternalFormat();

        return $this;
    }

    public function shiftTimeGradually($seconds, $from = 0, $till = null)
    {
        if ($till === null) {
            $till = $this->maxTime();
        }

        foreach ($this->internal_format as &$block) {
            $block = Helpers::shiftBlockTime($block, $seconds, $from, $till);
        }
        unset($block);

        $this->sortInternalFormat();

        return $this;
    }

    public function content($format)
    {
        $converter = Helpers::getConverterByFormat($format);
        $content = $converter->internalFormatToFileContent($this->internal_format);

        return $content;
    }

    // for testing only
    public function getInternalFormat()
    {
        return $this->internal_format;
    }

    // for testing only
    public function setInternalFormat(array $internal_format)
    {
        $this->internal_format = $internal_format;

        return $this;
    }

    // -------------------------------------- private ------------------------------------------------------------------

    protected function sortInternalFormat()
    {
        usort($this->internal_format, function ($item1, $item2) {
            return $item1['start'] <=> $item2['start'];
        });
    }

    public function maxTime()
    {
        $max_time = 0;
        foreach ($this->internal_format as $block) {
            if ($max_time < $block['end']) {
                $max_time = $block['end'];
            }
        }

        return $max_time;
    }

    protected function shouldBlockBeRemoved($block, $from, $till)
    {
        return ($from < $block['start'] && $block['start'] < $till) || ($from < $block['end'] && $block['end'] < $till);
    }

    public static function loadFromFile($path, $format = null)
    {
        if (!file_exists($path)) {
            throw new \Exception("file doesn't exist: " . $path);
        }

        $string = file_get_contents($path);

        return static::loadFromString($string, $format);
    }

    public static function loadFromString($string, $format = null)
    {
        $converter = new static;
        $string = Helpers::convertToUtf8($string);
        $string = Helpers::removeUtf8Bom($string);
        $string = Helpers::normalizeNewLines($string);
        $converter->input = $string;

        if ($format) {
            $input_converter = Helpers::getConverterByFormat($format);
        } else {
            $input_converter = Helpers::getConverterByFileContent($converter->input);
        }
        $internal_format = $input_converter->fileContentToInternalFormat($converter->input);

        // trim lines
        foreach ($internal_format as &$row) {
            foreach ($row['lines'] as &$line) {
                $line = trim($line);
            }
        }
        unset($row);
        unset($line);

        // remove blocks without text
        foreach ($internal_format as $k => $row) {
            if (trim($row['lines'][0]) === '') {
                unset($internal_format[$k]);
            }
        }
        unset($row);
        unset($k);

        // if empty captions
        if (count($internal_format) === 0) {
            $converter_name = explode('\\', $input_converter::class);
            throw new UserException('Subtitles were not found in this file (' . end($converter_name) . ')');
        }

        // exception if caption is showing for more than 5 minutes
        foreach ($internal_format as $row) {
            if ($row['end'] - $row['start'] > (60 * 5)) {
                throw new UserException('Error: line duration is longer than 5 minutes: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
            }
        }

        // reorder by time
        usort($internal_format, function ($a, $b) {
            if ($a['start'] === $b['start']) {
                return $a['end'] <=> $b['end'];
            }
            return $a['start'] <=> $b['start'];
        });

        // merge if the same start time
        $tmp = $internal_format;
        $internal_format = [];
        $i = 0;
        foreach ($tmp as $k => $row) {
            if ($k === 0) {
                $internal_format[$i] = $row;
                $i++;
                continue;
            }
            if ($row['start'] === $internal_format[$i - 1]['start']) {
                $internal_format[$i - 1]['lines'] = array_merge($internal_format[$i - 1]['lines'], $row['lines']);
                $max_end = max($row['end'], $internal_format[$i - 1]['end']);
                $internal_format[$i - 1]['end'] = $max_end;
            } else {
                $internal_format[$i] = $row;
                $i++;
            }

        }

        // fix up to a 60 seconds time overlap
        foreach ($internal_format as $k => $row) {
            if ($k === 0) {
                continue;
            }
            $diff = $internal_format[$k - 1]['end'] - $row['start'];
            if ($diff < 60 && $diff > 0) {
                $internal_format[$k - 1]['end'] = $row['start'];
            }
        }
        unset($row);

        // check if time is increasing
        $last_end_time = 0;
        foreach ($internal_format as $k => $row) {
            if ($row['start'] < $last_end_time) {
                throw new UserException("Timestamps are overlapping over 60 seconds: \nxx:xx:xx,xxx --> " . SrtConverter::internalTimeToSrt($internal_format[$k - 1]['end']) . ' ' .  $internal_format[$k - 1]['lines'][0] . "\n" . SrtConverter::internalTimeToSrt($row['start']) . ' --> xx:xx:xx,xxx ' . $row['lines'][0]);
            }
            $last_end_time = $row['end'];
            if ($row['start'] > $row['end']) {
                throw new UserException('Problem with timestamps, probably start time is bigger than the end time near text: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
            }
        }

        // first key is zero
        if (array_key_first($internal_format) !== 0) {
            throw new \Exception('First internal_array element is not a 0');
        }

        $converter->internal_format = $internal_format;

        return $converter;
    }
}