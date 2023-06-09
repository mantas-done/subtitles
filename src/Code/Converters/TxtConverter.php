<?php

namespace Done\Subtitles\Code\Converters;

class TxtConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        // user must explicitly specify that this is a simple text file
        // because it doesn't have any signs by which we can recognize that it is not other format
        return false;
    }

    public function fileContentToInternalFormat($file_content)
    {
        $internal_format = [];

        $blocks = explode("\n", trim($file_content));
        $i = 0;
        foreach ($blocks as $block) {
            $text = trim($block);
            if (strlen($text) === 0) {
                continue;
            }

            $internal_format[] = [
                'start' => $i,
                'end' => $i + 1,
                'lines' => [$text],
            ];
            $i++;
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
        $file_content = '';

        foreach ($internal_format as $block) {
            $line = implode(" ", $block['lines']);

            $file_content .= $line . "\r\n";
        }

        return trim($file_content);
    }

}
