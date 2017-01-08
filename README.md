# Subtitle Converter for PHP
Converts subtitle files from one format to another. Example: .srt to .stl...

## Example
Best way to learn is to see example. Let's convert .srt file to .stl:

```
SubtitleConverter::convert('subtitles.srt', 'subtitles.stl');
```

## Currently supported formats

```
.srt  
.stl

```

## How to add new subtitle format?

You need to implement ConverterContract.php interface. It has two methods.
```
fileContentToInternalFormat($file_content)  
  
internalFormatToFileContent($internal_format)
```

Basically what your implementation should be able to do, is convert subtitle file to "internal library's format", and from internal library's format back to subtitle file.

"Internal library's" format is used like middle ground, to be able to convert between different formats.

Best example is to look how SrtConverter.php is implemented.

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
