<?php

namespace Done\Subtitles;

use Done\Subtitles\Code\Converters\AssConverter;
use Done\Subtitles\Code\Converters\ConverterContract;
use Done\Subtitles\Code\Converters\CsvConverter;
use Done\Subtitles\Code\Converters\DfxpConverter;
use Done\Subtitles\Code\Converters\DocxReader;
use Done\Subtitles\Code\Converters\EbuStlReader;
use Done\Subtitles\Code\Converters\LrcConverter;
use Done\Subtitles\Code\Converters\RtfReader;
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
use Done\Subtitles\Code\Exceptions\DisableStrictSuggestionException;
use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;

/**
 * @phpstan-type Options array{strict?: bool, output_format?: string, fps?: float, ndf?: bool}
 */
class Subtitles
{
    protected string $input;

    /** @var array<array{start: float, end: float, lines: array<string>}> */
    protected array $internal_format; // data in internal format (when file is converted)

    protected ConverterContract $converter;

    /** @var array<int, array{extension: string, format: string, name: string, class: class-string}> */
    private array $formats = [
        ['extension' => 'ass',  'format' => 'ass',              'name' => 'Advanced Sub Station Alpha', 'class' => AssConverter::class],
        ['extension' => 'ssa',  'format' => 'ass',              'name' => 'Advanced Sub Station Alpha', 'class' => AssConverter::class],
        ['extension' => 'dfxp', 'format' => 'dfxp',             'name' => 'Netflix Timed Text',         'class' => DfxpConverter::class],
        ['extension' => 'sbv',  'format' => 'sbv',              'name' => 'YouTube',                    'class' => SbvConverter::class],
        ['extension' => 'vtt',  'format' => 'vtt',              'name' => 'WebVTT',                     'class' => VttConverter::class],
        ['extension' => 'srt',  'format' => 'srt',              'name' => 'SubRip',                     'class' => SrtConverter::class],
        ['extension' => 'stl',  'format' => 'stl',              'name' => 'Spruce Subtitle File',       'class' => StlConverter::class],
        ['extension' => 'stl',  'format' => 'ebu_stl',          'name' => 'EBU STL',                    'class' => EbuStlReader::class], // text needs to be converted from iso6937 encoding. PHP doesn't support it natively
        ['extension' => 'sub',  'format' => 'sub_microdvd',     'name' => 'MicroDVD',                   'class' => SubMicroDvdConverter::class],
        ['extension' => 'sub',  'format' => 'sub_subviewer',    'name' => 'SubViewer2.0',               'class' => SubViewerConverter::class],
        ['extension' => 'ttml', 'format' => 'ttml',             'name' => 'TimedText 1.0',              'class' => TtmlConverter::class],
        ['extension' => 'xml',  'format' => 'ttml',             'name' => 'TimedText 1.0',              'class' => TtmlConverter::class],
        ['extension' => 'smi',  'format' => 'smi',              'name' => 'SAMI',                       'class' => SmiConverter::class],
        ['extension' => 'txt',  'format' => 'txt_quicktime',    'name' => 'Quick Time Text',            'class' => TxtQuickTimeConverter::class],
        ['extension' => 'scc',  'format' => 'scc',              'name' => 'Scenarist',                  'class' => SccConverter::class],
        ['extension' => 'lrc',  'format' => 'lrc',              'name' => 'LyRiCs',                     'class' => LrcConverter::class],
        ['extension' => 'docx', 'format' => 'docx',             'name' => 'DOCX',                       'class' => DocxReader::class],
        ['extension' => 'rtf',  'format' => 'rtf',              'name' => 'Rich text format',           'class' => RtfReader::class], // libraryies eather throws exception, not parses, or takes long to parse 2h file
        ['extension' => 'csv',  'format' => 'csv',              'name' => 'Coma Separated Values',      'class' => CsvConverter::class], // must be last from bottom
        ['extension' => 'txt',  'format' => 'txt',              'name' => 'Plaintext',                  'class' => TxtConverter::class], // must be the last one
    ];

    /**
     * @param Options $options
     *
     * @throws UserException
     */
    public function convert(string $from_file_path, string $to_file_path, array $options = []): void
    {
        $output_format = null;
        if (isset($options['output_format'])) {
            // do nothing
        }
        if (isset($options['fps'])) {
            // do nothing
        }
        if (isset($options['ndf'])) {
            // do nothing
        }
        $strict = true;
        if (isset($options['strict']) && $options['strict'] == false) {
            $strict = $options['strict'];
            unset($options['strict']);
        }
        $this->loadFromFile($from_file_path, $strict)->save($to_file_path, $options);
    }

    /** @param array{output_format?: string} $options */
    public function save(string $path, array $options = []): self
    {
        $file_extension = Helpers::fileExtension($path);
        $format = $file_extension;
        if (isset($options['output_format'])) {
            $format = $options['output_format'];
        }
        $content = $this->content($format, $options);

        file_put_contents($path, $content);

        return $this;
    }

    /** @param string|array<string> $text */
    public function add(float $start, float $end, string|array $text): self
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

    public function trim(float $startTime, float $endTime): self
    {
        $this->remove(0, $startTime);
        $this->remove($endTime, $this->maxTime());

        return $this;
    }

    public function remove(float $from, float $till): self
    {
        foreach ($this->internal_format as $k => $block) {
            if ($this->shouldBlockBeRemoved($block, $from, $till)) {
                unset($this->internal_format[$k]);
            }
        }

        $this->internal_format = array_values($this->internal_format); // reorder keys

        return $this;
    }

    public function shiftTime(float $seconds, float $from = 0, ?float $till = null): self
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

    public function shiftTimeGradually(float $seconds, float $from = 0, ?float $till = null): self
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

    /** @param array{fps?: float} $options */
    public function content(string $format, array $options = []): string
    {
        $converter = Helpers::getConverterByFormat($this->formats, $format);
        $content = $converter->internalFormatToFileContent($this->internal_format, $options);

        return $content;
    }

    /**
     * @return array{extension: string, format: string, name: string, class: class-string}
     *
     * @throws UserException
     */
    public function getFormat(string $string): array
    {
        $modified_string = Helpers::convertToUtf8($string);
        $modified_string = Helpers::removeUtf8Bom($modified_string);
        $modified_string = Helpers::normalizeNewLines($modified_string);

        $input_converter = Helpers::getConverterByFileContent($this->formats, $modified_string, $string);

        foreach ($this->formats as $format) {
            if ($format['class'] === get_class($input_converter)) {
                return $format;
            }
        }

        throw new \RuntimeException('No foramt: ' . $string);
    }

    /** @return array<int, array{extension: string, format: string, name: string, class: class-string}> */
    public function getFormats(): array
    {
        return $this->formats;
    }

    public function registerConverter(string $class, string $string_format, string $extension, string $name): void
    {
        // unset class if the name of format is the same
        foreach ($this->formats as $k => $format) {
            if ($format['format'] === $string_format) {
                unset($this->formats[$k]);
            }
        }
        unset($format);

        // add at the beginning
        array_unshift($this->formats, ['extension' => $extension, 'format' => $string_format, 'name' => $name, 'class' => $class]);
    }

    /** @return array<int, array{start: float, end: float, lines: array<string>}> */
    public function getInternalFormat(): array
    {
        return $this->internal_format;
    }

    /** @param array<int, array{start: float, end: float, lines: array<string>}> $internal_format */
    public function setInternalFormat(array $internal_format): self
    {
        $this->internal_format = $internal_format;

        return $this;
    }

    // -------------------------------------- private ------------------------------------------------------------------

    protected function sortInternalFormat(): void
    {
        usort($this->internal_format, function ($item1, $item2) {
            return $item1['start'] <=> $item2['start'];
        });
    }

    public function maxTime(): float
    {
        $max_time = 0;
        foreach ($this->internal_format as $block) {
            if ($max_time < $block['end']) {
                $max_time = $block['end'];
            }
        }

        return $max_time;
    }

    /** @param array{start: float, end: float, lines: array<string>} $block */
    protected function shouldBlockBeRemoved(array $block, float $from, float $till): bool
    {
        return ($from < $block['start'] && $block['start'] < $till) || ($from < $block['end'] && $block['end'] < $till);
    }

    /** @throws UserException */
    public function loadFromFile(string $path, bool $strict = true): self
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("File doesn't exist");
        }

        $string = file_get_contents($path);
        if ($string === false) {
            throw new \RuntimeException("Problem opening file");
        }

        return $this->loadFromString($string, $strict);
    }

    /** @throws UserException */
    public function loadFromString(string $string, bool $strict = true): self
    {
        $modified_string = Helpers::convertToUtf8($string);
        $modified_string = Helpers::removeUtf8Bom($modified_string);
        $modified_string = Helpers::normalizeNewLines($modified_string);
        $this->input = $modified_string;

        $input_converter = Helpers::getConverterByFileContent($this->formats, $this->input, $string);
        $internal_format = $input_converter->fileContentToInternalFormat($this->input, $string);

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

        if ($internal_format[0]['start'] < 0) {
            if ($strict) {
                throw new DisableStrictSuggestionException('Start time is a negative number ' . SrtConverter::internalTimeToSrt($internal_format[0]['start']) . ' -> ' . SrtConverter::internalTimeToSrt($internal_format[0]['end']) . ' ' . $internal_format[0]['lines'][0]);
            } else {
                unset($internal_format[0]);
            }
        }

        // exception if caption is showing for more than 5 minutes
        foreach ($internal_format as $k => $row) {
            if ($row['end'] - $row['start'] > (60 * 5)) {
                if ($strict) {
                    throw new DisableStrictSuggestionException('Error: line duration is longer than 5 minutes: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
                } else {
                    unset($internal_format[$k]);
                }
            }
        }

        // check if time is increasing
        $last_end_time = 0;
        foreach ($internal_format as $k => $row) {
            if ($row['start'] < $last_end_time) {
                if ($strict) {
                    throw new DisableStrictSuggestionException("Timestamps are overlapping over 60 seconds: \nxx:xx:xx,xxx --> " . SrtConverter::internalTimeToSrt($internal_format[$k - 1]['end']) . ' ' .  $internal_format[$k - 1]['lines'][0] . "\n" . SrtConverter::internalTimeToSrt($row['start']) . ' --> xx:xx:xx,xxx ' . $row['lines'][0]);
                } else {
                    unset($internal_format[$k]);
                }
            }
            $last_end_time = $row['end'];
            if ($row['start'] > $row['end']) {
                if ($strict) {
                    throw new DisableStrictSuggestionException('Timestamp start time is bigger than the end time near text: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
                } else {
                    unset($internal_format[$k]);
                }
            }
            if ($row['start'] == $row['end']) {
                if ($strict) {
                    throw new DisableStrictSuggestionException('Timestamp start and end times are equal near text: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
                } else {
                    unset($internal_format[$k]);
                }
            }
        }

        // no subtitles with a lot of lines
        if (
            get_class($input_converter) === AssConverter::class
            || (get_class($input_converter) === TxtConverter::class && !TxtConverter::doesFileUseTimestamps(explode("\n", $this->input)))
        ) {
            // do nothing
        } else {
            foreach ($internal_format as $k => $row) {
                if (
                    count($row['lines']) > 10
                ) {
                    if ($strict) {
                        throw new DisableStrictSuggestionException('Over 10 lines of text selected, something is wrong with timestamps below this text: ' . SrtConverter::internalTimeToSrt($row['start']) . ' -> ' . SrtConverter::internalTimeToSrt($row['end']) . ' ' . $row['lines'][0]);
                    } else {
                        unset($internal_format[$k]);
                    }
                }
            }
        }

        $this->internal_format = array_values($internal_format);
        return $this;
    }
}