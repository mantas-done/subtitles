<?php

namespace Done\Subtitles\Code\Converters;

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
    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
        $internal_format = []; // array - where file content will be stored

        // $file_content = mb_convert_encoding($file_content, 'HTML');
        // in the future 'HTML' parameter will be deprecated, so use this function instead
        // https://github.com/mantas-done/subtitles/issues/87
        $file_content = mb_encode_numericentity($file_content, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');

        if (strpos($file_content, '</SYNC>') === false) {
            $file_content = str_replace('<SYNC ', '</SYNC><SYNC ', $file_content);
        }
        $file_content = str_replace("\n", '', $file_content);
        $file_content = str_replace("\t", '', $file_content);
        $file_content = preg_replace('/>\s+</', '><', $file_content);

        $doc = new \DOMDocument();
        @$doc->loadHTML($file_content); // silence warnings about invalid html

        $syncElements = $doc->getElementsByTagName('sync');

        foreach ($syncElements as $syncElement) {
            $time = $syncElement->getAttribute('start');

            foreach ($syncElement->childNodes as $childNode) {
                $lines = [];
                $line = '';
                if ($childNode->nodeName === 'p' || $childNode->nodeName === '#text') {
                    $line = $doc->saveHTML($childNode);
                    $line = preg_replace('/<br\s*\/?>/', '<br>', $line); // normalize <br>
                    $line = str_replace("\u{00a0}", '', $line); // no brake space - &nbsp;
                    $line = str_replace("&amp;nbsp", '', $line); // somebody didn't have semicolon at the end of &nbsp
                    $line = trim($line);
                    $lines = explode('<br>', $line);
                    $lines = array_map('strip_tags', $lines);
                    $lines = array_map('trim', $lines);
                    break;
                }
            }

            $data[] = [
                'start' => static::timeToInternal($time),
                'is_nbsp' => trim(strip_tags($line)) === '',
                'lines' => $lines,
            ];
        }

        $i = 0;
        foreach ($data as $row) {
            if (!isset($internal_format[$i - 1]['end']) && $i !== 0) {
                $internal_format[$i - 1]['end'] = $row['start'];
            }
            if (!$row['is_nbsp']) {
                $internal_format[$i] = [
                    'start' => $row['start'],
                    'lines' => $row['lines'],
                ];
                $i++;
            }
        }
        if (!isset($internal_format[$i - 1]['end'])) {
            $internal_format[$i - 1]['end'] = $internal_format[$i - 1]['start'] + 2.067; // SubtitleEdit adds this time if there is no last nbsp block
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
                $file_content .= '<SYNC Start=' . self::internalTimeToFormat($last_time) . '><P Class=ENUSCC>&nbsp;' . "</P></SYNC>\r\n";
            }

            $file_content .= '<SYNC Start=' . self::internalTimeToFormat($block['start']) . '><P Class=ENUSCC>' . implode('<br>', $block['lines']) .  "</P></SYNC>\r\n";
            $last_time = $block['end'];
        }
        $file_content .= '<SYNC Start=' . self::internalTimeToFormat($last_time) . '><P Class=ENUSCC>&nbsp;' . "</P></SYNC>\r\n";

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
        $time = $format_time / 1000;
        if ($time < 0) {
            $time = 0;
        }
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
    protected static function internalTimeToFormat($internal_time)
    {
       return round($internal_time * 1000);
    }
}
