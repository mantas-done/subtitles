<?php namespace Done\Subtitles;

interface SubtitleContract {

    public static function convert($from_file_path, $to_file_path);

    public static function load($file_name_or_file_content, $extension = null); // load file
    public function save($file_name); // save file
    public function content($format); // output file content (instead of saving to file)

    public function add($start, $end, $text); // add one line or several
    public function remove($from, $till); // delete text from subtitles
    public function time($seconds, $from = null, $till = null); // add or subtract some amount of seconds from all times

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
        if (strstr($file_name_or_file_content, "\n") === false) {
            return static::loadFile($file_name_or_file_content);
        } else {
            if (!$extension) {
                throw new \Exception('Specify extension');
            }
            return static::loadString($file_name_or_file_content, $extension);
        }
    }

    public function save($path)
    {
        $file_extension = static::fileExtension($path);
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
        usort($this->internal_format, function ($item1, $item2) {
            if ($item2['start'] == $item1['start']) {
                return 0;
            } elseif ($item2['start'] < $item1['start']) {
                return 1;
            } else {
                return -1;
            }
        });

        return $this;
    }

    public function remove($from, $till)
    {
        foreach ($this->internal_format as $k => $block) {
            if (($from < $block['start'] && $block['start'] < $till) || ($from < $block['end'] && $block['end'] < $till)) {
                unset($this->internal_format[$k]);
            }
        }

        $this->internal_format = array_values($this->internal_format); // reorder keys

        return $this;
    }

    public function time($seconds, $from = null, $till = null)
    {
        foreach ($this->internal_format as &$block) {
            if (!$this->shouldTimeBeAdded($from, $till, $block['start'], $block['end'])) {
                continue;
            }

            $block['start'] += $seconds;
            $block['end'] += $seconds;
        }
        unset($block);

        return $this;
    }

    public function content($format)
    {
        $format = strtolower(trim($format, '.'));

        $converter = static::getConverter($format);
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

    private static function shouldTimeBeAdded($from, $till, $block_start, $block_end)
    {
        if ($from !== null &&  $block_end < $from) {
            return false;
        }
        if ($till !== null && $till < $block_start) {
            return false;
        }

        return true;
    }

    private static function loadFile($path, $extension = null)
    {
        if (!file_exists($path)) {
            throw new \Exception("file doesn't exist: " . $path);
        }

        $string = file_get_contents($path);
        if (!$extension) {
            $extension = static::fileExtension($path);
        }

        return static::loadString($string, $extension);
    }

    private static function loadString($text, $extension)
    {
        $converter = new static;
        $converter->input = static::normalizeNewLines(static::removeUtf8Bom($text));

        $converter->input_format = $extension;

        $input_converter = static::getConverter($extension);
        $converter->internal_format = $input_converter->fileContentToInternalFormat($converter->input);

        return $converter;
    }

    public static function removeUtf8Bom($text)
    {
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);

        return $text;
    }

    private static function getConverter($extension)
    {
        $class_name = ucfirst($extension) . 'Converter';

        if (!file_exists('./src/code/Converters/' . $class_name . '.php')) {
            throw new \Exception('unknown format: ' . $extension);
        }

        $full_class_name = "\\Done\\Subtitles\\" . $class_name;

        return new $full_class_name();
    }

    private static function fileExtension($filename) {
        $parts = explode('.', $filename);
        $extension = end($parts);
        $extension = strtolower($extension);

        return $extension;
    }

    private static function normalizeNewLines($file_content)
    {
        $file_content = str_replace("\r\n", "\n", $file_content);
        $file_content = str_replace("\r", "\n", $file_content);

        return $file_content;
    }
}

// https://github.com/captioning/captioning has potential, but :(
// https://github.com/snikch/captions-php too small
