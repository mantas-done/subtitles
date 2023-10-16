<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;

class TtmlConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        $first_line = explode("\n", $file_content)[0];

        return
            (strpos($file_content, 'xmlns="http://www.w3.org/ns/ttml"') !== false && strpos($file_content, 'xml:id="d1"') === false)
            || preg_match('/<\?xml /m', $first_line) === 1
        ;
    }

    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($file_content);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        if (!empty($errors)) {
            if (Helpers::strContains($errors[0]->message, 'Document labelled UTF-16 but has UTF-8 content')) {
                $new_file_content = str_replace('encoding="utf-16"', 'encoding="utf-8"', $file_content);
                $new_file_content = str_replace('encoding="UTF-16"', 'encoding="UTF-8"', $new_file_content);
                $new_file_content = str_replace("encoding='utf-16'", "encoding='utf-8'", $new_file_content);
                $new_file_content = str_replace("encoding='UTF-16'", "encoding='UTF-8'", $new_file_content);
                return (new TtmlConverter())->fileContentToInternalFormat($new_file_content, '');
            }
            throw new UserException('Invalid XML: ' . trim($errors[0]->message));
        }

        $fps = self::framesPerSecond($file_content);
        if (preg_match('/DCSubtitle/', $file_content) === 1) {
            return self::DCSubtitles($file_content, $fps);
        }
        $divElements = $dom->getElementsByTagName('div');
        if (!$divElements->count() && $dom->getElementsByTagName('Subtitle')->count()) {
            return self::subtitleXml($file_content, $fps);
        }
        if (!$divElements->count() && $dom->getElementsByTagName('transcript')->count()) {
            return self::subtitleXml2($file_content);
        }
        if ($divElements->count() < 1) {
            $divElements = $dom->getElementsByTagName('body');
        }
        if ($divElements->count() < 1) {
            return [];
        }

        $internal_format = [];
        foreach ($divElements as $element) {
            $div_begin = $element->getAttribute('begin');
            $div_end = $element->getAttribute('end');
            foreach ($element->getElementsByTagName('p') as $pElement) {
                $begin = null;
                if ($pElement->hasAttribute('begin')) {
                    $begin = $pElement->getAttribute('begin');
                } elseif ($pElement->getAttribute('t')) {
                    $begin = $pElement->getAttribute('t');
                } elseif ($div_begin) {
                    $begin = $div_begin;
                }
                $begin = static::ttmlTimeToInternal($begin, $fps);

                $end = null;
                if ($pElement->hasAttribute('end')) {
                    $end = $pElement->getAttribute('end');
                } elseif ($div_end) {
                    $end = $div_end;
                }
                if ($end) {
                    $end = static::ttmlTimeToInternal($end, $fps);
                } elseif ($pElement->hasAttribute('dur') && $pElement->getAttribute('dur')) {
                    $end = $begin + static::ttmlTimeToInternal($pElement->getAttribute('dur'), $fps);
                } elseif ($pElement->hasAttribute('d') && $pElement->getAttribute('d')) {
                    $end = $begin + static::ttmlTimeToInternal($pElement->getAttribute('d'), $fps);
                }
                $lines = '';

                foreach ($pElement->childNodes as $node) {
                    if ($node->nodeType === XML_TEXT_NODE) {
                        $lines .= $node->nodeValue;
                    } else {
                        $lines .= $dom->saveXML($node); // Preserve HTML tags
                    }
                }

                $lines = self::getLinesFromTextWithBr($lines);

                $internal_format[] = array(
                    'start' => $begin,
                    'end' => $end ? $end : null,
                    'lines' => $lines,
                );
            }
        }

        $internal_format = TxtConverter::fillStartAndEndTimes($internal_format);

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
            $lines = array_map(function($line) {
                return htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
            }, $block['lines']);
            $lines = implode("<br />", $lines);

            $file_content .= "      <p begin=\"{$start}s\" xml:id=\"p{$k}\" end=\"{$end}s\">{$lines}</p>\n";
        }

        $file_content .= '    </div>
  </body>
</tt>';

        $file_content = str_replace("\r", "", $file_content);
        $file_content = str_replace("\n", "\r\n", $file_content);

        return $file_content;
    }

    public static function ttmlTimeToInternal($ttml_time, $frame_rate)
    {
        if (trim($ttml_time) === '') {
            throw new UserException("Timestamps were not found in this file (TtmlConverter)");
        }

        if (substr($ttml_time, -1) === 't') { // if last symbol is "t"
            // parses 340400000t
            return substr($ttml_time, 0, -1) / 10000000;
        } elseif (substr($ttml_time, -1) === 's') {
            return rtrim($ttml_time, 's');
        } elseif (substr($ttml_time, -1) === 'f' && $frame_rate) {
            $seconds = rtrim($ttml_time, 'f');
            return $seconds / $frame_rate;
        } elseif (preg_match('/(\d{2}):(\d{2}):(\d{2}):(\d{3})/', $ttml_time, $matches)) {
            $hours = intval($matches[1]);
            $minutes = intval($matches[2]);
            $seconds = intval($matches[3]);
            $milliseconds = intval($matches[4]);

            $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds + ($milliseconds / 1000);

            return $totalSeconds;
        } elseif (preg_match('/(\d{2}):(\d{2}):(\d{2}):(\d{2})/', $ttml_time, $matches)) {
            $hours = intval($matches[1]);
            $minutes = intval($matches[2]);
            $seconds = intval($matches[3]);
            $frames = intval($matches[4]);

            $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds + $frames / $frame_rate;

            return $totalSeconds;
        } elseif (is_numeric($ttml_time)) {
            return $ttml_time / 1000;
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

    private static function DCSubtitles(string $file_content, $fps)
    {
        $xml = simplexml_load_string($file_content);

        $internal_format = [];
        $subtitles = $xml->xpath('//Subtitle');
        foreach ($subtitles as $subtitle) {
            $internal_format[] = array(
                'start' => self::ttmlTimeToInternal((string)$subtitle['TimeIn'], $fps),
                'end' => self::ttmlTimeToInternal((string)$subtitle['TimeOut'], $fps),
                'lines' => self::getLinesFromTextWithBr((string)$subtitle->Text->asXML()),
            );
        }

        return $internal_format;
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

    /**
     * @param string $file_content
     * @return float|null
     */
    protected static function framesPerSecond(string $file_content)
    {
        $frameRate = null;
        preg_match('/ttp:frameRate="(\d+)"/', $file_content, $matches);
        if (isset($matches[1])) {
            $frameRate = $matches[1];
        }

        preg_match('/ttp:frameRateMultiplier="(\d+) (\d+)"/', $file_content, $matches);
        if (isset($matches[1]) && isset($matches[2])) {
            $numerator = $matches[1];
            $denominator = $matches[2];
        }

        if ($frameRate && isset($numerator) && isset($denominator)) {
            return $frameRate / $denominator * $numerator;
        } else if ($frameRate) {
            return $frameRate;
        }

        // calculate framerate automatically
        preg_match_all('/\d{2}:\d{2}:\d{2}:(\d{2})/', $file_content, $matches);
        $max_fps = 25;
        if (count($matches[1])) {
            foreach ($matches[1] as $tmp_fps) {
                if ($tmp_fps > $max_fps) {
                    $max_fps = $tmp_fps;
                }
            }
            return $max_fps + 1;
        }

        // when no framerate is specified
        return null;
    }

    private static function subtitleXml(string $file_content, $fps)
    {
        $xml = simplexml_load_string($file_content);

        $internal_format = [];

        foreach ($xml->Paragraph as $paragraph) {
             $subtitle = [
                'start' => (int)$paragraph->StartMilliseconds / 1000,
                'end' => (int)$paragraph->EndMilliseconds / 1000,
                'lines' => self::getLinesFromTextWithBr($paragraph->Text->asXML()),
            ];
            $internal_format[] = $subtitle;
        }

        if (count($internal_format) === 0) {
            // Select and process subtitle data
            $xml = simplexml_load_string($file_content);

            $namespace = $xml->getNamespaces(true)[''];
            $xml->registerXPathNamespace('ns', $namespace);

            $subtitles = $xml->xpath('//ns:Subtitle');
            foreach ($subtitles as $subtitle) {
                $internal_format[] = [
                    'start' => self::ttmlTimeToInternal((string)$subtitle['TimeIn'], $fps),
                    'end' => self::ttmlTimeToInternal((string)$subtitle['TimeOut'], $fps),
                    'lines' => self::getLinesFromTextWithBr($subtitle->Text->asXML()),
                ];
            }
        }

        return $internal_format;
    }

    private static function subtitleXml2(string $file_content)
    {
        $xml = simplexml_load_string($file_content);

        $internal_format = [];

        foreach ($xml->text as $text) {
            $attributes = $text->attributes();
            $internal_format[] = array(
                'start' => (string) $attributes['start'],
                'end' => (float)((string) $attributes['start'] + (string) $attributes['dur']),
                'lines' => self::getLinesFromTextWithBr(str_replace("\n", "<br>", $text->asXML()))
            );
        }

        return $internal_format;
    }

    private static function getLinesFromTextWithBr(string $text)
    {

        $text = preg_replace('/<br\s*\/?>/', '<br>', $text); // normalize <br>*/
        $lines = preg_replace('/<tt:br*\/?>/', '<br>', $text); // normalize <br>*/
        $lines = str_replace('<br>', "\n", $lines);
        $lines = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $lines); // remove zero width space characters
        $lines = explode("\n", $lines);
        $lines = array_map('strip_tags', $lines);
        $lines = array_map('trim', $lines);

        return $lines;
    }
}