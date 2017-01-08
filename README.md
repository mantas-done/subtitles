# Subtitle Converter for PHP
It is used to converts subtitle files from one format to another. Example: .srt to .stl...

# Example
Best way to learn is to see example. Let's convert .srt file to .stl:

```
SubtitleConverter::convert('subtitles.srt', 'subtitles.stl');
```
# How to add new subtitle format?

You need to implement ConverterContract.php interface. It has two methods.
```
parse($string) - it gets file content and you need to parse it and convert it into library's "internal format" (array).

convert($internal_format) - this method receives array (in "internal format") and you need to convert this array to how file content should look like and return it as string.
```

Best example is to look at StlConverter.php (it converts .stl files).

## "Internal Format" 

"Internal Format" is just a PHP array. It is used internally in library to be able to convert between different formats.

  [start] - from when text should be shown  
  [end] - till when text should be shown  
  [lines] - one or more text lines

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
