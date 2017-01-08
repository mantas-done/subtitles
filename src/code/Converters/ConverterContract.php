<?php namespace Done\SubtitleConverter;

interface ConverterContract {

    /**
     * Convert file content (.srt, .stl... file) to library's "internal format"
     *
     * @param string $file_content      Content of file that will be converted
     * @return array                    Internal format
     */
    public function fileContentToInternalFormat($file_content);

    /**
     * Convert library's "internal format" to file content
     *
     * @param array $internal_format    Internal format array
     * @return string                   Converted file content
     */
    public function internalFormatToFileContent($internal_format);

}