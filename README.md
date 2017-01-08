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

Basically what your implementation should be able to do, is convert subtitle file to "internal library's format" and from internal libary's format to subtitle file.

For example, if this library would not had support for .srt file format and we wanted to add it we would need to implement two things.
1. Ability to convert .srt file to internal library's format
2. Convert this internal format back to .srt file  

So by using this "internal format" we are unifying how files are converted. This way we first convert .srt file to "internal format" and then we can convert "internal format" to any other implemented file format.

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
[start] - when to start showing text (seconds, float)
[end] - when to stop showing text (seconds, float)
[lines] - one or more text lines (array of strings, each string in array contains separate line)
```