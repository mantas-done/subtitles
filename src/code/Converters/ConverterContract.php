<?php namespace Done\SubtitleConverter;

interface ConverterContract {

    /**
     * srt, stl to internal format
     *
     * @param $string
     * @return array  Internal format
     */
    public function parse($string);

    /**
     * Internal format to srt, stl
     *
     * @param $internal_format
     * @return string  SRT, STL..
     */
    public function convert($internal_format);


}