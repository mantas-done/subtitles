<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Code\UserException;
use Done\Subtitles\Subtitles;
use Jstewmc\Rtf\Document;

class RtfReader implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return strpos($file_content, '{\rtf1') === 0;
    }

    public function fileContentToInternalFormat($file_content, $original_file_content)
    {
        // https://stackoverflow.com/a/63029792/4126621
        $text = preg_replace("/(\{.*\})|}|(\\\\(?!')\S+)/m", '', $original_file_content);

        // remove backslashes
        $lines = mb_split("\n", $text);
        foreach ($lines as &$line) {
            $line = trim($line);
            $line = rtrim($line, '\\');
        }
        unset($line);
        $text = implode("\n", $lines);

        return Subtitles::loadFromString($text)->getInternalFormat();
    }

    public function internalFormatToFileContent(array $internal_format, array $options)
    {
        throw new \Exception('not implemented');
    }


}