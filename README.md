# Caption And Subtitle Converter for PHP

Convert and edit subtitles and captions.

## Supported formats

| Format | Extension |
| --- | --- |
| [SubRip](https://en.wikipedia.org/wiki/SubRip#SubRip_text_file_format) | .srt |
| [WebVTT](https://en.wikipedia.org/wiki/WebVTT) | .vtt |
| [SAMI](https://documentation.apple.com/en/dvdstudiopro/usermanual/index.html#chapter=19%26section=13%26tasks=true) | .stl |
| [Youtube Subtitles](https://webdev-il.blogspot.lt/2010/01/sbv-file-format-for-youtube-subtitles.html) | .sbv |
| [SubViewer](https://en.wikipedia.org/wiki/SubViewer) | .sub |

## Code Examples
Convert .srt file to .vtt:
```php
Subtitles::convert('subtitles.srt', 'subtitles.vtt');
```

Manually create file
```php
$subtitles = new Subtitles();
$subtitles->add(0, 5, 'This text is shown in the beggining of video for 5 seconds');
$subtitles->save('subtitles.vtt');
```

Load subtitles from existing file
```php
$subtitles = Subtitles::load('subtitles.srt');
```

Load subtitles from string
```php
$string = "
1
00:02:17,440 --> 00:02:20,375
Senator, we're making
our final approach into Coruscant.";  

$subtitles = Subtitles::load($string);
```

Save subtitles to file
```php
$subtitles->save('subtitler.vtt');
```

Get file content without saving to file
```php
$subtitles->content('vtt');
```

Add subtitles
```php
$subtitles->add(0, 5, 'some text'); // from 0, till 5 seconds
````

Remove subtitles
```php
$subtitles->remove(0, 5); // from 0, till 5 seconds
```

Add 1 second to subtitle time
```php
$subtitles->time(1);
```

Subtract 0.5 seconds from subtitle time
```php
$subtitles->time(-0.5);
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

## Contribution

You can contribute in any way you want. If you need some guidance, choose something from this table:

| Task | Difficulty | Description |
| --- | --- | --- |
| Add new formats | Medium | Supporting more formats is nice. Some popular formats: .mcc, .ttml, .qt.txt, .dfxp, .cap  |
| Refactor [StlConverter.php](https://github.com/mantas783/subtitle-converter/blob/master/src/code/Converters/StlConverter.php) file | Easy | .stl format is very similar to .srt. The only problem is that StlConverter.php code can be simplified a lot (check [SrtConverter.php](https://github.com/mantas783/subtitle-converter/blob/master/src/code/Converters/SrtConverter.php) as example) |
| Add .scc format | Hard | [Format description](https://en.wikipedia.org/wiki/EIA-608) |

For now library should support only basic features (several lines of text). No need to support different text styles or positioning of text.

## Running Tests

Simplest way is to download and put phpunit.phar file into the main directory of the project. Then run the command:

```
php phpunit.phar
```