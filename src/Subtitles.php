<?php namespace Done\Subtitles;

interface SubtitleContract {

    public static function convert($from_file_path, $to_file_path);

    public static function load($file_name_or_file_content, $extension = null); // load file
    public function save($file_name); // save file
    public function content($format); // output file content (instead of saving to file)

    public function add($start, $end, $text); // add one line or several
    public function remove($from, $till); // delete text from subtitles
    public function shiftTime($seconds, $from = 0, $till = null); // add or subtract some amount of seconds from all times
    public function shiftTimeGradually($seconds_to_shift, $from = 0, $till = null);

    public function getInternalFormat();
    public function setInternalFormat(array $internal_format);
}


class Subtitles implements SubtitleContract {

    protected $input;
    protected $input_format;

    protected $internal_format; // data in internal format (when file is converted)

    protected $converter;
    protected $output;

    public static function convert($from_file_path, $to_file_path)
    {
        static::load($from_file_path)->save($to_file_path);
    }

    public static function load($file_name_or_file_content, $extension = null)
    {
        if (file_exists($file_name_or_file_content)) {
            return static::loadFile($file_name_or_file_content);
        }

        return static::loadString($file_name_or_file_content, $extension);
    }

    public function save($path)
    {
        $file_extension = Helpers::fileExtension($path);
        $content = $this->content($file_extension);

        file_put_contents($path, $content);

        return $this;
    }

    public function add($start, $end, $text)
    {
        // @TODO validation
        // @TODO check subtitles to not overlap
        $this->internal_format[] = [
            'start' => $start,
            'end' => $end,
            'lines' => is_array($text) ? $text : [$text],
        ];

        $this->sortInternalFormat();

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
        $format = strtolower(trim($format, '.'));

        $converter = Helpers::getConverter($format);
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

    /**
     * @deprecated  Use shiftTime() instead
     */
    public function time($seconds, $from = null, $till = null)
    {
        return $this->shiftTime($seconds, $from, $till);
    }

    // -------------------------------------- private ------------------------------------------------------------------

    protected function sortInternalFormat()
    {
        usort($this->internal_format, function($item1, $item2) {
            if ($item2['start'] == $item1['start']) {
                return 0;
            } elseif ($item2['start'] < $item1['start']) {
                return 1;
            } else {
                return -1;
            }
        });
    }

    protected function maxTime()
    {
        $max_time = 0;
        foreach ($this->internal_format as $block) {
            if ($max_time < $block['end']) {
                $max_time = $block['end'];
            }
        }

        return $max_time;
    }

    protected function shouldBlockBeRemoved($block, $from, $till) {
        return ($from < $block['start'] && $block['start'] < $till) || ($from < $block['end'] && $block['end'] < $till);
    }

    public static function loadFile($path, $extension = null)
    {
        if (!file_exists($path)) {
            throw new \Exception("file doesn't exist: " . $path);
        }

        $string = file_get_contents($path);
        if (!$extension) {
            $extension = Helpers::fileExtension($path);
        }

        return static::loadString($string, $extension);
    }

    public static function loadString($text, $extension)
    {
        $converter = new static;
        $converter->input = Helpers::normalizeNewLines(Helpers::removeUtf8Bom($text));

        $converter->input_format = $extension;

        $input_converter = Helpers::getConverter($extension);
        $converter->internal_format = $input_converter->fileContentToInternalFormat($converter->input);

        return $converter;
    }
}

// https://github.com/captioning/captioning has potential, but :(
// https://github.com/snikch/captions-php too small
