# Caption And Subtitle Converter for PHP

🥳🎉👏 Probably the best subtitle parser 🥳🎉👏

💣 Tested on thousands of user submitted files 🤯  
🔥 Almost 100% unit test coverage 💥

## Supported formats

| Format | Extension | Internal format name |
| --- | --- | --- |
| [SubRip](https://en.wikipedia.org/wiki/SubRip#SubRip_text_file_format) | .srt | srt |
| [WebVTT](https://en.wikipedia.org/wiki/WebVTT) | .vtt | vtt |
| [Spruce Technologies SubTitles](https://pastebin.com/ykGM9qjZ) | .stl | stl |
| [EBU STL (only decoder)](https://tech.ebu.ch/docs/tech/tech3264.pdf) | .stl | ebu_stl |
| [Youtube Subtitles](https://webdev-il.blogspot.lt/2010/01/sbv-file-format-for-youtube-subtitles.html) | .sbv | sbv |
| [SubViewer](https://wiki.videolan.org/SubViewer) | .sub | sub_subviewer |
| [MicroDVD](https://en.wikipedia.org/wiki/MicroDVD) | .sub | sub_microdvd |
| Advanced Sub Station | .ass | ass |
| [Netflix Timed Text](https://en.wikipedia.org/wiki/Timed_Text_Markup_Language) | .dfxp | dfxp |
| [TTML](https://en.wikipedia.org/wiki/Timed_Text_Markup_Language) | .ttml | ttml |
| [SAMI](https://en.wikipedia.org/wiki/SAMI) | .smi | smi |
| QuickTime | .qt.txt | txt_quicktime |
| [Scenarist](http://www.theneitherworld.com/mcpoodle/SCC_TOOLS/DOCS/SCC_FORMAT.HTML) | .scc | scc |
| [LyRiCs](https://en.wikipedia.org/wiki/LRC_(file_format)) | .lrc | lrc |
| Comma separated values | .csv | csv |
| Plaintext | .txt | txt |

## Command line
Can be used from the command line to convert from .srt to .vtt
```
php subtitles.phar input.srt output.vtt
```
subtitles.phar file can be found here - https://github.com/mantas-done/subtitles/releases

## Installation (supports PHP 7.2+)
```
composer require mantas-done/subtitles
```

## Usage
Convert .srt file to .vtt:
```php
// add namespace
use \Done\Subtitles\Subtitles;

Subtitles::convert('subtitles.srt', 'subtitles.vtt');
```

```php
// if no input format is specified, library will determine file format by its content
// if third parameter is specified, library will convert the file to specified format.
// list of formats are in Subtitle::$formats, they are: ass, dfxp, sbv, srt, stl, sub, ttml, txt_quicktime, vtt 
Subtitles::convert('subtitles1', 'subtitles2', ['output_format' => 'vtt']); 
```

Manually create file
```php
$subtitles = new Subtitles();
$subtitles->add(0, 5, 'This text is shown in the beggining of video for 5 seconds');
$subtitles->save('subtitles.vtt');
```

Load subtitles from existing file
```php
$subtitles = Subtitles::loadFromFile('subtitles.srt');
```

Load subtitles from string
```php
$string = "
1
00:02:17,440 --> 00:02:20,375
Senator, we're making our final approach
";  

$subtitles = Subtitles::loadFromString($string, 'srt');
```

Save subtitles to file
```php
$subtitles->save('subtitler.vtt');
```

Get file content without saving to file
```php
echo $subtitles->content('vtt');
```

Add subtitles
```php
$subtitles->add(0, 5, 'some text'); // from 0, till 5 seconds  

// Add multiline text
$subtitles->add(0, 5, [
    'first line',
    'second line',
]);

// Add styles to VTT file format
// Only VTT supports styles currently
$subtitles->add(0, 5, 'text', ['vtt_cue_settings' => 'position:50% line:15% align:middle']);
````

Remove subtitles
```php
$subtitles->remove(0, 5); // from 0, till 5 seconds
```

Trim subtitles
```php
$subtitles->trim(3, 4); // get only from 3, till 4 seconds
```

Add 1 second to all subtitles
```php
$subtitles->shiftTime(1);
```

Subtract 0.5 second
```php
$subtitles->shiftTime(-0.5);
```

Add 5 second to subtitles starting from 1 minute till 2 mintes 
```php
$subtitles->shiftTime(5, 60, 120);
```

Example: shift time gradually by 2 seconds over 1 hour video. At the beginning of the video don't change time, in the middle shift time by 1 second. By the end of video, shift time by 2 seconds.
```php
$subtitles->shiftTimeGradually(2, 0, 3600);
```

## Exceptions ##

Library will throw UserException, it's message can be shown to the user.
```php
try {
    (new \Done\Subtitles\Subtitles())->add(0, 1, 'very long text... aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')->content('scc');
} catch (\Done\Subtitles\Code\UserException $e) {
    echo $e->getMessage(); // SCC file can't have more than 4 lines of text each 32 characters long. This text is too long: <text from user file that triggered this error>
}
```
By default, library tries to detect different file errors that can be shown to the user, so he would be able to fix them. 
If you want to relax the rules and allow the library to convert even somewhat invalid files, use ['strict' => false]
```php
Subtitles::convert($input, $output, ['strict' => false]);
Subtitles::loadFromString($string, ['strict' => false]);
Subtitles::loadFromFile($input, ['strict' => false]);
```

## How to add new subtitle format?

You need to implement ConverterContract.php interface. It has two methods.
```php
fileContentToInternalFormat($file_content)  
  
internalFormatToFileContent($internal_format)
```

Basically what your implementation should be able to do, is convert subtitle file to "internal library's format", and from internal library's format back to subtitle file.

"Internal library's" format is used like middle ground, to be able to convert between different formats.

Best example is to look how [SrtConverter.php](https://github.com/mantas783/subtitle-converter/blob/master/src/code/Converters/SrtConverter.php) is implemented.  
And this is example of [.srt file](https://github.com/mantas783/subtitle-converter/blob/master/tests/files/srt.srt).

### "Internal Format" 

"Internal Format" is just a PHP array. It is used internally in library to be able to convert between different formats.

```
Array
(
    [0] => Array
        (
            [start] => 137.44
            [end] => 140.375
            [lines] => Array
                (
                    [0] => Senator, we're making
                    [1] => our final approach into Coruscant.
                )
        )
    [1] => Array
        (
            [start] => 140.476
            [end] => 142.501
            [lines] => Array
                (
                    [0] => Very good, Lieutenant.
                )
        )
)
```
```
[start] - when to start showing text (float - seconds)
[end] - when to stop showing text (float -seconds)
[lines] - one or more text lines (array)
```

## Running Tests

```
php vendor/bin/phpunit
```

## Contribution

You can contribute in any way you want. If you need some guidance, choose something from this table:

| Task | Difficulty | Description |
| --- | --- | --- |
| Add new formats | Medium | Supporting more formats is nice. Some popular formats: .mcc, .cap |
| Refactor [StlConverter.php](https://github.com/mantas783/subtitle-converter/blob/master/src/code/Converters/StlConverter.php) file | Easy | .stl format is very similar to .srt. The only problem is that StlConverter.php code can be simplified a lot (check [SrtConverter.php](https://github.com/mantas783/subtitle-converter/blob/master/src/code/Converters/SrtConverter.php) as example) |
| Add .scc format | Hard | [Format description](http://www.theneitherworld.com/mcpoodle/SCC_TOOLS/DOCS/SCC_FORMAT.HTML) |

For now library should support only basic features (several lines of text). No need to support different text styles or positioning of text.

## Report Bugs

If some file is not working with the library, please create and issue and attach the file.
