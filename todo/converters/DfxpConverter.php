<?php

declare(strict_types=1);


namespace converters;

use Done\Subtitles\Providers\ConverterInterface;

class DfxpConverter implements ConverterInterface
{
    public function parseSubtitles(string $fileContent): array
    {
        preg_match_all('/<p.+begin="(?<start>[^"]+).*end="(?<end>[^"]+)[^>]*>(?<text>(?!<\/p>).+)<\/p>/', $fileContent, $matches, PREG_SET_ORDER);

        $internalFormat = [];
        foreach ($matches as $block) {
            $internalFormat[] = [
                'start' => static::dfxpTimeToInternal($block['start']),
                'end' => static::dfxpTimeToInternal($block['end']),
                'lines' => explode('<br/>', $block['text']),
            ];
        }

        return $internalFormat;
    }

    public function toSubtitles(array $internalFormat): string
    {
        $fileContent = '<?xml version="1.0" encoding="utf-8"?>
<tt xmlns="http://www.w3.org/ns/ttml" xmlns:ttm="http://www.w3.org/ns/ttml#metadata" xmlns:tts="http://www.w3.org/ns/ttml#styling" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <head>
    <metadata>
      <ttm:title>Netflix Subtitle</ttm:title>
    </metadata>
    <styling>
      <style tts:fontStyle="normal" tts:fontWeight="normal" xml:id="s1" tts:color="white" tts:fontFamily="Arial" tts:fontSize="100%"></style>
    </styling>
    <layout>
      <region tts:extent="80% 40%" tts:origin="10% 10%" tts:displayAlign="before" tts:textAlign="center" xml:id="topCenter" />
      <region tts:extent="80% 40%" tts:origin="10% 50%" tts:displayAlign="after" tts:textAlign="center" xml:id="bottomCenter" />
    </layout>
  </head>
  <body>
    <div style="s1" xml:id="d1">
';

        foreach ($internalFormat as $k => $block) {
            $nr = $k + 1;
            $start = static::internalTimeToDfxp($block['start']);
            $end = static::internalTimeToDfxp($block['end']);
            $lines = implode("<br/>", $block['lines']);

            $fileContent .= "    <p xml:id=\"p{$nr}\" begin=\"{$start}\" end=\"{$end}\" region=\"bottomCenter\">{$lines}</p>\n";
        }

        $fileContent .= '  </div>
  </body>
</tt>';

        $fileContent = str_replace("\r", "", $fileContent);
        $fileContent = str_replace("\n", "\r\n", $fileContent);

        return $fileContent;
    }

    /** private */
    protected static function internalTimeToDfxp(string $internalTime): string
    {
        $parts = explode('.', $internalTime); // 1.23
        $whole = $parts[0]; // 1
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : 0; // 23

        return gmdate("H:i:s", (int) floor($whole)) . ',' . str_pad($decimal, 3, '0', STR_PAD_RIGHT);
    }

    protected static function dfxpTimeToInternal(string $dfxpTime): float
    {
        $parts = explode(',', $dfxpTime);

        $onlySeconds = strtotime("1970-01-01 {$parts[0]} UTC");
        $milliseconds = (float) '0.' . $parts[1];

        return $onlySeconds + $milliseconds;
    }
}
