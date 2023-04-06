# Caption And Subtitle Converter for PHP

Convert and edit subtitles and captions.

## Supported formats

| Format                                                                                                | Extension |
|-------------------------------------------------------------------------------------------------------|-----------|
| [SubRip](https://en.wikipedia.org/wiki/SubRip#SubRip_text_file_format)                                | .srt      |
| [WebVTT](https://en.wikipedia.org/wiki/WebVTT)                                                        | .vtt      |
| [Youtube Subtitles](https://webdev-il.blogspot.lt/2010/01/sbv-file-format-for-youtube-subtitles.html) | .sbv      |

## Installation

```
composer require saeven/subtitles
```

## Fork Notes

This package was forked from `mantas-done/subtitles` (thank you) to become more opinionated, to implement strict types and new interfaces, and to implement proper PSR-4 loading.
What's more, this version of that package removes all ability to manipulate files directly or to attempt to infer the existence of converters by scanning the filesystem
with file_exists and so forth. It also resorts to pattern matching in cases where the original implementation would split strings. Lastly, it adds Carbon to perform
time conversions.

## Usage

Convert .srt content to .vtt:

```php
// add namespace
use \Circlical\Subtitles\Subtitles;

(new Subtitles())->load($srtContent, 'srt')->content('vtt');
```

Manually create VTT

```php
$subtitles = new Subtitles();
$subtitles->add(0, 5, 'This text is shown in the beggining of video for 5 seconds');
file_put_contents( './foo', $subtitles->content('subtitles.vtt'));
```

Load subtitles from string

```php
$string = "
1
00:02:17,440 --> 00:02:20,375
Senator, we're making our final approach
";  

$subtitles = Subtitles::load($string, 'srt');
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
````

Remove subtitles

```php
$subtitles->remove(0, 5); // from 0, till 5 seconds
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
./vendor/bin/phpunit
```

## Contribution

Not all original converters from the fork have been corrected. Feel free to convert one from the 'todo' folder and open a PR!

## Report Bugs

If some file is not working with the library, please create and issue and attach the file.
