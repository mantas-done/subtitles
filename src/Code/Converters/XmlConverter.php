<?php

namespace Done\Subtitles\Code\Converters;

class XmlConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return preg_match('/<\?xml /m', $file_content) === 1;
    }

    public function fileContentToInternalFormat($file_content)
    {
        $xml = simplexml_load_string($file_content);

        $internal_format = [];

        foreach ($xml->Paragraph as $paragraph) {
            $subtitle = [
                'start' => (int)$paragraph->StartMilliseconds / 1000,
                'end' => (int)$paragraph->EndMilliseconds / 1000,
                'lines' => [(string)$paragraph->Text],
            ];
            $internal_format[] = $subtitle;
        }

        if (empty($internal_format)) {
            foreach ($xml->body->div->p as $paragraph) {
                $text = $paragraph->asXML();
                $text = preg_replace('/<br\s*\/?>/', "\n", $text); // normalize <br>*/
                $text = strip_tags($text);
                $lines = explode("\n", $text);
                $subtitle = [
                    'start' => TxtConverter::timeToInternal((string)$paragraph['begin'], null),
                    'end' => TxtConverter::timeToInternal((string)$paragraph['end'], null),
                    'lines' => $lines,
                ];
                $internal_format[] = $subtitle;
            }
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format)
    {
        throw new \Exception('not implemented');
    }
}
