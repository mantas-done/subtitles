<?php

namespace Done\Subtitles;

use Done\Subtitles\Code\Converters\AssConverter;
use Done\Subtitles\Code\Converters\CsvConverter;
use Done\Subtitles\Code\Converters\DfxpConverter;
use Done\Subtitles\Code\Converters\EbuStlConverter;
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
        ['extension' => 'vtt',  'format' => 'vtt',              'name' => 'WebVTT',                     'class' => VttConverter::class],
        ['extension' => 'srt',  'format' => 'srt',              'name' => 'SubRip',                     'class' => SrtConverter::class],
        ['extension' => 'stl',  'format' => 'stl',              'name' => 'Spruce Subtitle File',       'class' => StlConverter::class],
        ['extension' => 'stl',  'format' => 'ebu_stl',          'name' => 'EBU STL',                    'class' => EbuStlConverter::class], // text needs to be converted from iso6937 encoding. PHP doesn't support it natively
        ['extension' => 'sub',  'format' => 'sub_microdvd',     'name' => 'MicroDVD',                   'class' => SubMicroDvdConverter::class],
        ['extension' => 'sub',  'format' => 'sub_subviewer',    'name' => 'SubViewer2.0',               'class' => SubViewerConverter::class],
        ['extension' => 'ttml', 'format' => 'ttml',             'name' => 'TimedText 1.0',              'class' => TtmlConverter::class],
        ['extension' => 'xml',  'format' => 'ttml',             'name' => 'TimedText 1.0',              'class' => TtmlConverter::class],
        ['extension' => 'smi',  'format' => 'smi',              'name' => 'SAMI',                       'class' => SmiConverter::class],
        ['extension' => 'txt',  'format' => 'txt_quicktime',    'name' => 'Quick Time Text',            'class' => TxtQuickTimeConverter::class],
        ['extension' => 'scc',  'format' => 'scc',              'name' => 'Scenarist',                  'class' => SccConverter::class],
        ['extension' => 'lrc',  'format' => 'lrc',              'name' => 'LyRiCs',                     'class' => LrcConverter::class],
        ['extension' => 'csv',  'format' => 'csv',              'name' => 'Coma Separated Values',      'class' => CsvConverter::class], // must be last from bottom
        ['extension' => 'txt',  'format' => 'txt',              'name' => 'Plaintext',                  'class' => TxtConverter::class], // must be the last one
    ];

    public static function convert($from_file_path, $to_file_path, $options = [])
    {
        $output_format = null;
        if (isset($options['output_format'])) {
            $output_format = $options['output_format'];
        }
        $strict = true;
        if (isset($options['strict']) && $options['strict'] == false) {
            $strict = (bool)$options['strict'];
        }
        static::loadFromFile($from_file_path, $strict)->save($to_file_path, $output_format);
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
        $internal_format = [
            'start' => $start,
            'end' => $end,
            'lines' => is_array($text) ? $text : [$text],
        ];

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

    public static function loadFromFile($path, $strict = true)
    {
        if (!file_exists($path)) {
            throw new \Exception("file doesn't exist: " . $path);
        }

        $string = file_get_contents($path);

        return static::loadFromString($string, $strict);
    }

    public static function loadFromString($string, $strict = true)
    {
        $converter = new static;
        $modified_string = Helpers::convertToUtf8($string);
        $modified_string = Helpers::removeUtf8Bom($modified_string);
        $modified_string = Helpers::normalizeNewLines($modified_string);
        $converter->input = $modified_string;

        $input_converter = Helpers::getConverterByFileContent($converter->input);
        $internal_format = $input_converter->fileContentToInternalFormat($converter->input, $string);

        // remove empty lines
        foreach ($internal_format as $k => $row) {
            $new_lines = [];
            foreach ($row['lines'] as $line) {
                if (trim($line) !== '') {
                    $new_lines[] = $line;
                }
            }
            $internal_format[$k]['lines'] = $new_lines;
        }

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
            if (!isset($row['lines'][0]) || trim($row['lines'][0]) === '') {
                unset($internal_format[$k]);
            }
        }
        unset($row);
        unset($k);

        // if empty captions
        if (count($internal_format) === 0) {
            $converter_name = explode('\\', get_class($input_converter));
            throw new UserException('Subtitles were not found in this file (' . end($converter_name) . ')');
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

        if (!$strict) {
            $converter->internal_format = $internal_format;
            return $converter;
        }

        // exception if caption is showing for more than 5 minutes
        foreach ($internal_format as $row) {
            if ($row['end'] - $row['start'] > (60 * 5)) {
                throw new UserException('Error: line duration is longer than 5 minutes: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
            }
        }

        if ($internal_format[0]['start'] < 0) {
            throw new UserException('Start time is a negative number ' . SrtConverter::internalTimeToSrt($internal_format[0]['start']) . ' -> ' . SrtConverter::internalTimeToSrt($internal_format[0]['end']) . ' ' . $internal_format[0]['lines'][0]);
        }

        // check if time is increasing
        $last_end_time = 0;
        foreach ($internal_format as $k => $row) {
            if ($row['start'] < $last_end_time) {
                throw new UserException("Timestamps are overlapping over 60 seconds: \nxx:xx:xx,xxx --> " . SrtConverter::internalTimeToSrt($internal_format[$k - 1]['end']) . ' ' .  $internal_format[$k - 1]['lines'][0] . "\n" . SrtConverter::internalTimeToSrt($row['start']) . ' --> xx:xx:xx,xxx ' . $row['lines'][0]);
            }
            $last_end_time = $row['end'];
            if ($row['start'] > $row['end']) {
                throw new UserException('Timestamp start time is bigger than the end time near text: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
            }
            if ($row['start'] == $row['end']) {
                throw new UserException('Timestamp start and end times are equal near text: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
            }
        }

        // no subtitles with a lot of lines
        if (
            get_class($input_converter) === AssConverter::class
            || (get_class($input_converter) === TxtConverter::class && !TxtConverter::doesFileUseTimestamps(mb_split("\n", $converter->input)))
        ) {
            // do nothing
        } else {
            foreach ($internal_format as $row) {
                if (
                    count($row['lines']) > 10
                ) {
                    throw new UserException('Over 10 lines of text selected, something is wrong with timestamps below this text: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
                }
            }
        }

        $converter->internal_format = $internal_format;
        return $converter;
    }
}