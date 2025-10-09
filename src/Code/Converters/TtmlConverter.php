<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Code\Helpers;

class TtmlConverter implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        $first_line = explode("\n", $file_content)[0];

        return
            (strpos($file_content, 'xmlns="http://www.w3.org/ns/ttml"') !== false && strpos($file_content, 'xml:id="d1"') === false)
            || preg_match('/<\?xml /m', $first_line) === 1
        ;
    }

    public function fileContentToInternalFormat(string $file_content, string $original_file_content, bool $strict): array
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
                return (new TtmlConverter())->fileContentToInternalFormat($new_file_content, '', $strict);
            }
            // throw new UserException('Invalid XML: ' . trim($errors[0]->message));
        }

        $xpath = new \DOMXPath($dom);

        $fps = self::framesPerSecond($file_content);
        if (preg_match('/DCSubtitle/', $file_content) === 1) {
            return self::DCSubtitles($file_content, $fps);
        }

        $div_nodes = $xpath->query("//*[local-name()='div']");
        $subtitle_nodes = $xpath->query("//*[local-name()='Subtitle']");
        $transcript_nodes = $xpath->query("//*[local-name()='transcript']");

        if (($div_nodes === false || $div_nodes->length === 0) && $subtitle_nodes !== false && $subtitle_nodes->length > 0) {
            return self::subtitleXml($file_content, $fps);
        }
        if (($div_nodes === false || $div_nodes->length === 0) && $transcript_nodes !== false && $transcript_nodes->length > 0) {
            return self::subtitleXml2($file_content);
        }
        if ($div_nodes === false || $div_nodes->length === 0) {
            $div_nodes = $xpath->query("//*[local-name()='body']");
        }
        if ($div_nodes === false || $div_nodes->length === 0) {
            return [];
        }

        $timed_span_nodes = $xpath->query("//*[local-name()='span' and (@begin or @end or @dur or @d)]");
        $has_timed_spans_globally = $timed_span_nodes !== false && $timed_span_nodes->length > 0;

        $internal_format = [];

        /** @var \DOMElement $element */
        foreach ($div_nodes as $element) {
            $div_begin = $element->hasAttribute('begin') ? $element->getAttribute('begin') : '';
            $div_end = $element->hasAttribute('end') ? $element->getAttribute('end') : '';

            $p_nodes = $xpath->query(".//*[local-name()='p']", $element);
            if ($p_nodes === false) {
                continue;
            }

            /** @var \DOMElement $pElement */
            foreach ($p_nodes as $pElement) {
                $begin_raw = null;
                if ($pElement->hasAttribute('begin')) {
                    $begin_raw = $pElement->getAttribute('begin');
                } elseif ($pElement->hasAttribute('t') && $pElement->getAttribute('t') !== '') {
                    $begin_raw = $pElement->getAttribute('t');
                } elseif ($div_begin !== '') {
                    $begin_raw = $div_begin;
                }
                $begin = ($begin_raw !== null && $begin_raw !== '') ? static::ttmlTimeToInternal($begin_raw, $fps) : null;

                $end_raw = null;
                if ($pElement->hasAttribute('end')) {
                    $end_raw = $pElement->getAttribute('end');
                } elseif ($div_end !== '') {
                    $end_raw = $div_end;
                }
                if ($end_raw !== null && $end_raw !== '') {
                    $end = static::ttmlTimeToInternal($end_raw, $fps);
                } elseif ($pElement->hasAttribute('dur') && $pElement->getAttribute('dur') !== '') {
                    $end = ($begin ?? 0) + static::ttmlTimeToInternal($pElement->getAttribute('dur'), $fps);
                } elseif ($pElement->hasAttribute('d') && $pElement->getAttribute('d') !== '') {
                    $end = ($begin ?? 0) + static::ttmlTimeToInternal($pElement->getAttribute('d'), $fps);
                } else {
                    $end = null;
                }

                // timed span segments within this <p>
                $span_segments = [];
                $span_nodes = $xpath->query(".//*[local-name()='span']", $pElement);
                if ($span_nodes !== false) {
                    /** @var \DOMElement $span */
                    foreach ($span_nodes as $span) {
                        $is_timed = $span->hasAttribute('begin') || $span->hasAttribute('end') || $span->hasAttribute('dur') || $span->hasAttribute('d');
                        if (!$is_timed) {
                            continue;
                        }

                        $seg_begin = $span->hasAttribute('begin')
                            ? static::ttmlTimeToInternal($span->getAttribute('begin'), $fps)
                            : $begin;

                        $seg_end = null;
                        if ($span->hasAttribute('end')) {
                            $seg_end = static::ttmlTimeToInternal($span->getAttribute('end'), $fps);
                        } elseif ($span->hasAttribute('dur')) {
                            $seg_end = ($seg_begin ?? 0) + static::ttmlTimeToInternal($span->getAttribute('dur'), $fps);
                        } elseif ($span->hasAttribute('d')) {
                            $seg_end = ($seg_begin ?? 0) + static::ttmlTimeToInternal($span->getAttribute('d'), $fps);
                        }

                        $span_xml = $dom->saveXML($span) ?: '';
                        $lines = array_map('trim', self::getLinesFromTextWithBr($span_xml));
                        $lines = array_values(array_filter($lines, fn($l) => $l !== ''));

                        if ($seg_begin !== null && !empty($lines)) {
                            $span_segments[] = [
                                'start' => $seg_begin,
                                'end' => $seg_end,
                                'lines' => $lines,
                            ];
                        }
                    }
                }

                if (!empty($span_segments)) {
                    // collect non-span content to emit once at <p> timing (if any)
                    $nonspan_xml = '';
                    foreach ($pElement->childNodes as $node) {
                        if (strtolower($node->nodeName) !== 'span') {
                            if ($node->nodeType === XML_TEXT_NODE) {
                                $nonspan_xml .= $node->nodeValue;
                            } else {
                                $nonspan_xml .= ($dom->saveXML($node) ?: '');
                            }
                        }
                    }
                    $nonspan_lines = array_map('trim', self::getLinesFromTextWithBr($nonspan_xml));
                    $nonspan_lines = array_values(array_filter($nonspan_lines, fn($l) => $l !== ''));

                    foreach ($span_segments as $seg) {
                        $internal_format[] = $seg;
                    }

                    if (!empty($nonspan_lines) && $begin !== null) {
                        $internal_format[] = [
                            'start' => $begin,
                            'end' => $end,
                            'lines' => $nonspan_lines,
                        ];
                    }

                    continue;
                }

                // no timed spans in this <p>; decide whether to keep or skip
                $has_span_children = ($span_nodes !== false && $span_nodes->length > 0);

                $has_direct_text = false;
                foreach ($pElement->childNodes as $node) {
                    if ($node->nodeType === XML_TEXT_NODE && trim((string)$node->nodeValue) !== '') {
                        $has_direct_text = true;
                        break;
                    }
                }

                // Skip only if: this <p> has ONLY untimed spans, there is no direct text,
                // AND the document elsewhere uses timed spans (Xml10 behavior).
                if ($has_span_children && !$has_direct_text && $has_timed_spans_globally) {
                    continue;
                }

                $lines_xml = '';
                foreach ($pElement->childNodes as $node) {
                    if ($node->nodeType === XML_TEXT_NODE) {
                        $lines_xml .= $node->nodeValue;
                    } else {
                        $lines_xml .= ($dom->saveXML($node) ?: '');
                    }
                }

                $lines = self::getLinesFromTextWithBr($lines_xml);

                $internal_format[] = [
                    'start' => $begin,
                    'end' => $end ? $end : null,
                    'lines' => $lines,
                ];
            }
        }

        $internal_format = TxtConverter::fillStartAndEndTimes($internal_format);

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
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
        if (trim((string)$ttml_time) === '') {
            throw new UserException("Timestamps were not found in this file (TtmlConverter)");
        }

        if (substr($ttml_time, -1) === 't') { // 340400000t
            return (int)substr($ttml_time, 0, -1) / 10000000;
        } elseif (substr($ttml_time, -2) === 'ms') { // 1500ms
            return (int)rtrim($ttml_time, 'ms') / 1000;
        } elseif (substr($ttml_time, -1) === 's') { // 1234s
            return rtrim($ttml_time, 's');
        } elseif (substr($ttml_time, -1) === 'f' && $frame_rate) { // 24f
            $seconds = rtrim($ttml_time, 'f');
            return $seconds / $frame_rate;
        } elseif (preg_match('/(\d{2}):(\d{2}):(\d{2}):(\d{3})/', $ttml_time, $matches)) { // 00:00:00:000
            $hours = intval($matches[1]);
            $minutes = intval($matches[2]);
            $seconds = intval($matches[3]);
            $milliseconds = intval($matches[4]);
            $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds + ($milliseconds / 1000);
            return $totalSeconds;
        } elseif (preg_match('/(\d{2}):(\d{2}):(\d{2}):(\d{2})/', $ttml_time, $matches)) { // 00:00:00:00
            $hours = intval($matches[1]);
            $minutes = intval($matches[2]);
            $seconds = intval($matches[3]);
            $frames = intval($matches[4]);
            $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds + $frames / $frame_rate;
            return $totalSeconds;
        } elseif (is_numeric($ttml_time)) { // 12345
            return $ttml_time / 1000;
        } else {
            $time_parts = explode('.', $ttml_time);
            $milliseconds = 0.0;
            if (isset($time_parts[1])) {
                $milliseconds = (float)('0.' . $time_parts[1]);
            }

            $values = array_map('intval', explode(':', $time_parts[0]));
            $hours = 0;
            $minutes = 0;

            $count = count($values);
            $seconds = $values[$count - 1] ?? 0;

            if (isset($values[$count - 2])) {
                $minutes = $values[$count - 2];
            }
            if (isset($values[$count - 3])) {
                $hours = $values[$count - 3];
            }

            return ($hours * 3600) + ($minutes * 60) + $seconds + $milliseconds;
        }
    }

    private static function DCSubtitles(string $file_content, $fps)
    {
        $xml = simplexml_load_string($file_content);

        $internal_format = [];
        $subtitles = $xml->xpath('//Subtitle');

        foreach ($subtitles as $subtitle) {
            $lines = [];
            foreach ($subtitle->Text as $line) {
                $tmp_lines = self::getLinesFromTextWithBr((string)$line->asXML());
                foreach ($tmp_lines as $tmp_line) {
                    $tmp_line = trim($tmp_line);
                    if ($tmp_line !== '') {
                        $lines[] = $tmp_line;
                    }
                }
            }

            $internal_format[] = [
                'start' => self::ttmlTimeToInternal((string)$subtitle['TimeIn'], $fps),
                'end'   => self::ttmlTimeToInternal((string)$subtitle['TimeOut'], $fps),
                'lines' => $lines,
            ];
        }

        return $internal_format;
    }

    // ---------------------------------- private ----------------------------------------------------------------------

    protected static function internalTimeToTtml($internal_time)
    {
        $formatted_output = round($internal_time, 3);

        if (strpos((string)$formatted_output, '.') === false) {
            $formatted_output .= '.0';
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
            $frameRate = (float)$matches[1];
        }

        preg_match('/ttp:frameRateMultiplier="(\d+) (\d+)"/', $file_content, $matches);
        if (isset($matches[1]) && isset($matches[2])) {
            $numerator = (float)$matches[1];
            $denominator = (float)$matches[2];
        }

        if ($frameRate && isset($numerator) && isset($denominator)) {
            return $frameRate / $denominator * $numerator;
        } elseif ($frameRate) {
            return $frameRate;
        }

        // calculate framerate automatically
        preg_match_all('/\d{2}:\d{2}:\d{2}:(\d{2})/', $file_content, $matches);
        $max_fps = 25;
        if (count($matches[1])) {
            foreach ($matches[1] as $tmp_fps) {
                if ($tmp_fps > $max_fps) {
                    $max_fps = (int)$tmp_fps;
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
            $lines = [];
            foreach ($paragraph->Text as $line) {
                $tmp_lines = self::getLinesFromTextWithBr((string)$line->asXML());
                foreach ($tmp_lines as $tmp_line) {
                    $lines[] = $tmp_line;
                }
            }
            $subtitle = [
                'start' => (int)$paragraph->StartMilliseconds / 1000,
                'end' => (int)$paragraph->EndMilliseconds / 1000,
                'lines' => $lines,
            ];
            $internal_format[] = $subtitle;
        }

        if (count($internal_format) === 0) {
            // Select and process subtitle data
            $xml = simplexml_load_string($file_content);

            $namespace_array = $xml->getNamespaces(true);
            $namespace = array_pop($namespace_array);
            $xml->registerXPathNamespace('ns', $namespace);

            $subtitles = $xml->xpath('//ns:Subtitle');
            foreach ($subtitles as $subtitle) {
                $text = $subtitle->Text->asXML();
                if ($text === false) {
                    $text = $subtitle->children('dcst', true)->Text->asXML();
                }
                $text = $text ?: '';
                $internal_format[] = [
                    'start' => self::ttmlTimeToInternal((string)$subtitle['TimeIn'], $fps),
                    'end' => self::ttmlTimeToInternal((string)$subtitle['TimeOut'], $fps),
                    'lines' => self::getLinesFromTextWithBr($text),
                ];
            }
        }

        return $internal_format;
    }

    private static function subtitleXml2(string $file_content)
    {
        $xml = simplexml_load_string($file_content);

        $internal_format = [];

        $i = 0;
        foreach ($xml->text as $text) {
            $attributes = $text->attributes();
            $end = null;
            if (isset($attributes['dur']) && (string)$attributes['dur'] !== '') {
                $end = (float)$attributes['start'] + (float)$attributes['dur'];
            }
            $xml_text = $text->asXML() ?: '';
            $internal_format[] = array(
                'start' => (string)$attributes['start'],
                'end' => $end,
                'lines' => self::getLinesFromTextWithBr(str_replace("\n", "<br>", $xml_text))
            );
            if ($i !== 0 && ($internal_format[$i - 1]['end']) === null) {
                $internal_format[$i - 1]['end'] = (float)$attributes['start'];
            }
            $i++;
        }
        if ($i !== 0 && $internal_format[$i - 1]['end'] === null) {
            // @phpstan-ignore-next-line
            $internal_format[$i - 1]['end'] = (float)$attributes['start'] + 1;
        }

        return $internal_format;
    }

    private static function getLinesFromTextWithBr(string $text)
    {
        $text = preg_replace('/<br\s*\/?>/i', '<br>', $text); // normalize <br>
        $lines = preg_replace('/<tt:br\s*\/?>/i', '<br>', $text); // normalize prefixed <tt:br>
        $lines = str_replace('<br>', "\n", $lines);
        $lines = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $lines); // remove zero width chars
        $lines = explode("\n", $lines);
        $lines = array_map('strip_tags', $lines);
        $lines = array_map('trim', $lines);

        return $lines;
    }
}
