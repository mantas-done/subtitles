# Caption And Subtitle Converter for PHP
This library helps to work with subtitles (captions):
1. Convert formats (example: .srt to .stl)
2. Create subtitles manually
3. Parse files
4. Time shift and edit subtitles (add some time or subtract time to synchronize captions)

## Example
Best way to learn is to see example. Let's convert .srt file to .stl:

```
Subtitles::convert('subtitles.srt', 'subtitles.stl');
```

## Currently supported formats

| Format | Extension | Implemented |
| --- | --- | --- |
| [SubRip](https://en.wikipedia.org/wiki/SubRip#SubRip_text_file_format) | .srt | yes |
| [SAMI](https://documentation.apple.com/en/dvdstudiopro/usermanual/index.html#chapter=19%26section=13%26tasks=true) | .stl | yes |

## How to add new subtitle format?

You need to implement ConverterContract.php interface. It has two methods.
```
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
| Add .sub, .sbv formats | Easy | Supporting more formats is nice. So implement this format. You can find how file should look like in [format description](https://en.wikipedia.org/wiki/SubViewer) |
| Refactor [StlConverter.php](https://github.com/mantas783/subtitle-converter/blob/master/src/code/Converters/StlConverter.php) file | Easy | .stl format is very similar to .srt. The only problem is that StlConverter.php code can be simplified a lot (check [SrtConverter.php](https://github.com/mantas783/subtitle-converter/blob/master/src/code/Converters/SrtConverter.php) as example) |
| Add .vtt format | Medium | [Format description}(https://en.wikipedia.org/wiki/WebVTT) |
| Add .scc format | Hard | [Format description](https://en.wikipedia.org/wiki/EIA-608) |

Some other popular formats: .mcc, .ttml, .qt.txt, .dfxp, .cap 

For now library should support only basic features (several lines of text). No need to support different text styles or positioning of text.

## Running Tests

Simplest way is to download and put phpunit.phar file into the main directory of the project. Then run the command:

```
php phpunit.phar
```

## TODO
1. Maybe should pass new Subtitles() into fileContentToInternalFormat function?