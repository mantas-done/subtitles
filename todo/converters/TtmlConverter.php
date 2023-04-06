<?php

declare(strict_types=1);


namespace converters;

use Done\Subtitles\Providers\ConverterInterface;

class TtmlConverter implements ConverterInterface
{
    public function parseSubtitles(string $fileContent): array
    {
        preg_match_all('/<p.+begin="(?<start>[^"]+).*end="(?<end>[^"]+)[^>]*>(?<text>(?!<\/p>).+)<\/p>/', $fileContent, $matches, PREG_SET_ORDER);

        $internalFormat = [];
        foreach ($matches as $block) {
            $internalFormat[] = [
                'start' => static::ttmlTimeToInternal($block['start']),
                'end' => static::ttmlTimeToInternal($block['end']),
                'lines' => explode('<br />', $block['text']),
            ];
        }

        return $internalFormat;
    }

    public function toSubtitles(array $internalFormat): string
    {
        $fileContent = '<?xml version="1.0" encoding="utf-8"?>
<tt xmlns="http://www.w3.org/ns/ttml" xmlns:ttp="http://www.w3.org/ns/ttml#parameter" ttp:timeBase="media" xmlns:tts="http://www.w3.org/ns/ttml#style" xml:lang="en" xmlns:ttm="http://www.w3.org/ns/ttml#metadata">
  <head>
    <metadata>
      <ttm:title></ttm:title>
    </metadata>
    <styling>
      <style id="s0" tts:backgroundColor="black" tts:fontStyle="normal" tts:fontSize="16" tts:fontFamily="sansSerif" tts:color="white" />
    </styling>
  </head>
  <body style="s0">
    <div>
';

        foreach ($internalFormat as $k => $block) {
            $start = static::internalTimeToTtml($block['start']);
            $end = static::internalTimeToTtml($block['end']);
            $lines = implode("<br />", $block['lines']);

            $fileContent .= "      <p begin=\"{$start}s\" id=\"p{$k}\" end=\"{$end}s\">{$lines}</p>\n";
        }

        $fileContent .= '    </div>
  </body>
</tt>';

        $fileContent = str_replace("\r", "", $fileContent);
        $fileContent = str_replace("\n", "\r\n", $fileContent);

        return $fileContent;
    }

    /** private */
    protected static function internalTimeToTtml(float $internalTime): string
    {
        return number_format($internalTime, 1, '.', '');
    }

    protected static function ttmlTimeToInternal(string $ttmlTime): string
    {
        return rtrim($ttmlTime, 's');
    }
}
