<?php

namespace Done\Subtitles\Code\Converters;

class XmlConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return preg_match('/^<\?xml /m', $file_content) === 1;
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

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format)
    {
        throw new \Exception('not implemented');
    }
}
