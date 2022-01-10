<?php

namespace Done\Subtitles;

class DfxpConverter implements ConverterContract
{
    /**
     * @var int The number of milliseconds in a second. This is the multiplier
     * used to convert seconds to milliseconds.
     */
    private const SECOND           = 1000;
    /**
     * @var int Time fraction for Netflix. This is the multiplier used
     * to multiply the milliseconds value of the timestamp for a given subtitles
     * text
     */
    private const NETFLIX_FRACTION = 10000;

    /**
     * 
     * @see ConverterContract::fileContentToInternalFormat()
     */
    public function fileContentToInternalFormat($file_content)
    {
        // Load the XML as DOM
        $domDocument = new \DOMDocument('1.0', 'UTF-8');
        $domDocument->loadXML($file_content);

        // Prepare the internal format array
        $internal_format = [];

        // Load subtiles lines
        $subtitlesList = $domDocument->getElementsByTagName('p');
        foreach ($subtitlesList as $subLine) {
            // Get begin time
            $beginTime = trim($subLine->getAttribute('begin'), 't');
            // Get end time
            $endTime   = trim($subLine->getAttribute('end'), 't');

            $internal_format[] = [
                'start' => $beginTime / (static::SECOND * static::NETFLIX_FRACTION),
                'end'   => $endTime / (static::SECOND * static::NETFLIX_FRACTION),
                'lines' => explode('<br/>', strip_tags($subLine->ownerDocument->saveXML($subLine), '<br>'))
            ];
        }
        
        return $internal_format;
    }

    /**
     * @see ConverterContract::internalFormatToFileContent()
     */
    public function internalFormatToFileContent(array $internal_format)
    {
        $subtitlesString = '';
        // Iterate through all lines and generate the corresponding DFXP entries
        foreach ($internal_format as $entry) {
            // Convert the start time to DFXP time
            $beginTime = $entry['start'] * static::SECOND * static::NETFLIX_FRACTION;
            // Convert the end time to DFXP time
            $endTime   = $entry['end'] * static::SECOND * static::NETFLIX_FRACTION;
            // Join all the lines into a single string
            $text      = implode('<br/>', $entry['lines']);

            $subtitlesString .= sprintf('<p begin="%dt" end="%dt">%s</p>', $beginTime, $endTime, $text) . "\n";
        }


        // Get the wrapper for the XML and  put the subtitles line in it
        $wrapperXML = static::getWRapperDFXPWrapper();
        return str_replace('@@SUBTITLES GO HERE', $subtitlesString, $wrapperXML);
    }

    private static function getWRapperDFXPWrapper()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<tt xmlns:tt="http://www.w3.org/ns/ttml" xmlns:ttm="http://www.w3.org/ns/ttml#metadata" xmlns:ttp="http://www.w3.org/ns/ttml#parameter" xmlns:tts="http://www.w3.org/ns/ttml#styling" ttp:cellResolution="40 19" ttp:pixelAspectRatio="1 1" ttp:tickRate="10000000" ttp:timeBase="media" tts:extent="640px 480px" xmlns="http://www.w3.org/ns/ttml">
	<head>
		<ttp:profile use="http://netflix.com/ttml/profile/dfxp-ls-sdh"/>
		<styling>
			<style tts:color="white" tts:fontFamily="monospaceSansSerif" tts:fontSize="100%" xml:id="bodyStyle"/>
			<style tts:color="white" tts:fontFamily="monospaceSansSerif" tts:fontSize="100%" tts:fontStyle="italic" xml:id="style_0"/>
		</styling>
		<layout>
			<region xml:id="region_00">
				<style tts:textAlign="left"/>
				<style tts:displayAlign="center"/>
			</region>
			<region xml:id="region_01">
				<style tts:textAlign="left"/>
				<style tts:displayAlign="center"/>
			</region>
			<region xml:id="region_02">
				<style tts:textAlign="left"/>
				<style tts:displayAlign="center"/>
			</region>
		</layout>
	</head>
	<body style="bodyStyle">
		<div xml:space="preserve">
			
			
			@@SUBTITLES GO HERE
			
			
		</div>
	</body>
</tt>
';
    }

}