<?php

namespace Done\Subtitles\Code\Converters;

class TtmlConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return strpos($file_content, 'xmlns="http://www.w3.org/ns/ttml"') !== false && strpos($file_content, 'xml:id="d1"') === false;
    }

    public function fileContentToInternalFormat($file_content)
    {
        $dom = new \DOMDocument();
        @$dom->loadXML($file_content);

        $fps = self::framesPerSecond($dom);
        $array = array();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            throw new \Exception('no body');
        }
        $div = $body->getElementsByTagName('div')->item(0);
        if (!$div) {
            throw new \Exception('no div');
        }
        $pElements = $div->getElementsByTagName('p');
        foreach ($pElements as $p) {
            $begin = $p->getAttribute('begin');
            $end = $p->getAttribute('end');
            $lines = '';

            $textNodes = $p->childNodes;
            foreach ($textNodes as $node) {
                if ($node->nodeType === XML_TEXT_NODE) {
                    $lines .= $node->nodeValue;
                } else {
                    $lines .= $dom->saveXML($node); // Preserve HTML tags
                }
            }

            $lines = preg_replace('/<br\s*\/?>/', '<br>', $lines); // normalize <br>*/
            $lines = explode('<br>', $lines);
            $lines = array_map('strip_tags', $lines);
            $lines = array_map('trim', $lines);

            $array[] = array(
                'start' => static::ttmlTimeToInternal($begin, $fps),
                'end' => static::ttmlTimeToInternal($end, $fps),
                'lines' => $lines,
            );
        }

        return $array;





        preg_match_all('/<p.+begin="(?<start>[^"]+).*end="(?<end>[^"]+)[^>]*>(?<text>(?!<\/p>).+)<\/p>/', $file_content, $matches, PREG_SET_ORDER);

        $internal_format = [];
        foreach ($matches as $block) {
            $internal_format[] = [
                'start' => static::ttmlTimeToInternal($block['start']),
                'end' => static::ttmlTimeToInternal($block['end']),
                'lines' => explode('<br />', $block['text']),
            ];
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format)
    {
        $file_content = '<?xml version="1.0" encoding="utf-8"?>
<tt xmlns="http://www.w3.org/ns/ttml" xmlns:ttp="http://www.w3.org/ns/ttml#parameter" ttp:timeBase="media" xmlns:tts="http://www.w3.org/ns/ttml#styling" xml:lang="en" xmlns:ttm="http://www.w3.org/ns/ttml#metadata">
  <head>
    <metadata>
      <ttm:title></ttm:title>
    </metadata>
    <styling>
      <style xml:id="s0" tts:backgroundColor="black" tts:fontStyle="normal" tts:fontSize="16px" tts:fontFamily="sansSerif" tts:color="white" />
    </styling>
    <layout>
      <region tts:extent="80% 40%" tts:origin="10% 10%" tts:displayAlign="before" tts:textAlign="start" xml:id="topLeft" />
      <region tts:extent="80% 40%" tts:origin="10% 30%" tts:displayAlign="center" tts:textAlign="start" xml:id="centerLeft" />
      <region tts:extent="80% 40%" tts:origin="10% 50%" tts:displayAlign="after" tts:textAlign="start" xml:id="bottomLeft" />
      <region tts:extent="80% 40%" tts:origin="10% 10%" tts:displayAlign="before" tts:textAlign="center" xml:id="topCenter" />
      <region tts:extent="80% 40%" tts:origin="10% 30%" tts:displayAlign="center" tts:textAlign="center" xml:id="centerСenter" />
      <region tts:extent="80% 40%" tts:origin="10% 50%" tts:displayAlign="after" tts:textAlign="center" xml:id="bottomCenter" />
      <region tts:extent="80% 40%" tts:origin="10% 10%" tts:displayAlign="before" tts:textAlign="end" xml:id="topRight" />
      <region tts:extent="80% 40%" tts:origin="10% 30%" tts:displayAlign="center" tts:textAlign="end" xml:id="centerRight" />
      <region tts:extent="80% 40%" tts:origin="10% 50%" tts:displayAlign="after" tts:textAlign="end" xml:id="bottomRight" />
    </layout>
  </head>
  <body style="s0">
    <div>
';

        foreach ($internal_format as $k => $block) {
            $start = static::internalTimeToTtml($block['start']);
            $end = static::internalTimeToTtml($block['end']);
            $lines = implode("<br />", $block['lines']);

            $file_content .= "      <p begin=\"{$start}s\" xml:id=\"p{$k}\" end=\"{$end}s\">{$lines}</p>\n";
        }

        $file_content .= '    </div>
  </body>
</tt>';

        $file_content = str_replace("\r", "", $file_content);
        $file_content = str_replace("\n", "\r\n", $file_content);

        return $file_content;
    }

    public static function ttmlTimeToInternal($ttml_time, $frame_rate = null)
    {
        if (substr($ttml_time, -1) === 't') { // if last symbol is "t"
            // parses 340400000t
            return substr($ttml_time, 0, -1) / 10000000;
        } elseif (substr($ttml_time, -1) === 's') {
            return rtrim($ttml_time, 's');
        } elseif (substr($ttml_time, -1) === 'f' && $frame_rate) {
            $seconds = rtrim($ttml_time, 'f');
            return $seconds / $frame_rate;
        } else {
            $time_parts = explode('.', $ttml_time);
            $milliseconds = 0;
            if (isset($time_parts[1])) {
                $milliseconds = (float) ('0.' . $time_parts[1]);
            }

            list($hours, $minutes, $seconds) = array_map('intval', explode(':', $time_parts[0]));
            return ($hours * 3600) + ($minutes * 60) + $seconds + $milliseconds;
        }
    }

    // ---------------------------------- private ----------------------------------------------------------------------

    protected static function internalTimeToTtml($internal_time)
    {
        $formatted_output =  round($internal_time, 3);

        if (strpos($formatted_output, '.') === false) {
            $formatted_output .= ".0";  // Add at least one digit after decimal if there are no digits
        }

        return $formatted_output;
    }

    protected static function framesPerSecond($dom)
    {
        $ttElement = $dom->getElementsByTagName('tt')->item(0);
        $frameRate = $ttElement?->getAttributeNS('http://www.w3.org/ns/ttml#parameter', 'frameRate');
        $frameRateMultiplier = $ttElement?->getAttributeNS('http://www.w3.org/ns/ttml#parameter', 'frameRateMultiplier');

        if ($frameRate && $frameRateMultiplier) {
            list($numerator, $denominator) = array_map('intval', explode(' ', $frameRateMultiplier));
            return $frameRate / $denominator * $numerator;
        } else if ($frameRate) {
            return (int) $frameRate;
        }

        //This is a standard frame rate used in many video formats and broadcast television.
        return 30;
    }
}