<?php namespace Done\Subtitles;

class SmiConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return preg_match('/<SAMI>/m', $file_content) === 1;
    }

    /**
     * Converts file's content (.srt) to library's "internal format" (array)
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content)
    {
        $internal_format = []; // array - where file content will be stored

        $tmp_block = null;
        $pattern = '/<SYNC Start=(\d+)><P Class=ENUSCC>(.*)/';
        preg_match_all($pattern, $file_content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $time = $match[1];
            $text = str_replace('<\P>', '', $match[2]);

            if ($text === '&nbsp;') {
                $internal_format[] = [
                    'start' => static::timeToInternal($tmp_block['start']),
                    'end' => static::timeToInternal($time),
                    'lines' => explode("<br>", $tmp_block['text']),
                ];
            } else {
                $tmp_block = ['start' => $time, 'text' => $text];
            }
        }

        return $internal_format;
    }

    /**
     * Convert library's "internal format" (array) to file's content
     *
     * @param array $internal_format    Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internal_format)
    {
        $file_content = '<SAMI>
<HEAD>
<TITLE>file</TITLE>
<SAMIParam>
  Metrics {time:ms;}
  Spec {MSFT:1.0;}
</SAMIParam>
<STYLE TYPE="text/css">
<!--
  P { font-family: Arial; font-weight: normal; color: white; background-color: black; text-align: center; }
  .ENUSCC { name: English; lang: en-US ; SAMIType: CC ; }
-->
</STYLE>
</HEAD>
<BODY>
<-- Open play menu, choose Captions and Subtitles, On if available -->
<-- Open tools menu, Security, Show local captions when present -->
';

        // <SYNC Start=137400><P Class=ENUSCC>Senator, we're making<br>our final approach into Coruscant.
        //<SYNC Start=140400><P Class=ENUSCC>&nbsp;
        $last_time = null;
        foreach ($internal_format as $block) {
            if ($last_time !== null && $last_time !== $block['start']) {
                $file_content .= '<SYNC Start=' . self::internalTimeToFormat($last_time) . '><P Class=ENUSCC>&nbsp;' . "\r\n";
            }

            $file_content .= '<SYNC Start=' . self::internalTimeToFormat($block['start']) . '><P Class=ENUSCC>' . implode('<br>', $block['lines']) . "\r\n";
            $last_time = $block['end'];
        }
        $file_content .= '<SYNC Start=' . self::internalTimeToFormat($last_time) . '><P Class=ENUSCC>&nbsp;' . "\r\n";

        $file_content .= '</BODY>
</SAMI>';

        return $file_content;
    }

    // ------------------------------ private --------------------------------------------------------------------------

    /**
     * Convert .srt file format to internal time format (float in seconds)
     * Example: 00:02:17,440 -> 137.44
     *
     * @param $format_time
     *
     * @return float
     */
    protected static function timeToInternal($format_time)
    {
        return $format_time / 1000;
    }

    /**
     * Convert internal time format (float in seconds) to .srt time format
     * Example: 137.44 -> 00:02:17,440
     *
     * @param float $internal_time
     *
     * @return string
     */
    protected static function internalTimeToFormat($internal_time)
    {
       return round($internal_time * 1000);
    }
}
