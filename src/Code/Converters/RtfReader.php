<?php

namespace Done\Subtitles\Code\Converters;

use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Subtitles;
use Jstewmc\Rtf\Document;

class RtfReader implements ConverterContract
{
    public function canParseFileContent(string $file_content, string $original_file_content): bool
    {
        return strpos($file_content, '{\rtf1') === 0;
    }

    public function fileContentToInternalFormat(string $file_content, string $original_file_content): array
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

        return (new Subtitles())->loadFromString($text)->getInternalFormat();
    }

    /** @throws UserException */
    public function internalFormatToFileContent(array $internal_format , array $output_settings): string
    {
        throw new UserException('RTF writer is not implemented yet');
    }


}