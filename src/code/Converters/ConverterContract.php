<?php namespace Done\Subtitles;

interface ConverterContract {

    /**
     * Converts file content (.srt, .stl... file content) to library's "internal format"
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content);

    /**
     * Convert library's "internal format" to file's content
     *
     * @param array $internal_format    Internal format
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent(array $internal_format);

}