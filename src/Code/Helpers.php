<?php

namespace Done\Subtitles\Code;

use Done\Subtitles\Code\Converters\ConverterContract;
use Done\Subtitles\Code\Exceptions\UserException;
use Done\Subtitles\Subtitles;

class Helpers
{
    public static function shouldBlockTimeBeShifted(float $from, ?float $till, float $block_start, float $block_end): bool
    {
        if ($block_end < $from) {
            return false;
        }

        if ($till === null) {
            return true;
        }

        return $till >= $block_start;
    }

    public static function removeUtf8Bom(string $text): string
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^($bom)+/", '', $text); // some files have multiple BOM at the beginning
        if ($text === null) {
            throw new \RuntimeException("Couldn't remove BOM");
        }

        return $text;
    }

    /** @param array<int, array{extension: string, format: string, name: string, class: class-string}> $formats */
    public static function getConverterByFormat(array $formats,string $format): ConverterContract
    {
        foreach ($formats as $row) {
            if ($row['format'] === $format) {
                $full_class_name = $row['class'];
                /** @var ConverterContract $converter */
                $converter = new $full_class_name();
                return $converter;
            }
        }

        throw new \RuntimeException("Can't find suitable converter, for format: $format");
    }

    /**
     * @param array<int, array{extension: string, format: string, name: string, class: class-string}> $formats
     * @throws UserException
     */
    public static function getConverterByFileContent(array $formats, string $file_content, string $original_file_content): ConverterContract
    {
        foreach ($formats as $row) {
            $class_name = $row['class'];
            $full_class_name = $class_name;
            /** @var ConverterContract $converter */
            $converter = new $full_class_name();
            if ($converter->canParseFileContent($file_content, $original_file_content)) {
                return $converter;
            }
        }

        throw new UserException("Can't find suitable converter for the file");
    }

    public static function fileExtension(string $filename): string {
        $parts = explode('.', $filename);
        $extension = end($parts);
        $extension = strtolower($extension);

        return $extension;
    }

    public static function normalizeNewLines(string $file_content): string
    {
        $file_content = str_replace("\r\n", "\n", $file_content);
        $file_content = str_replace("\r", "\n", $file_content);

        return $file_content;
    }

    /** @throws UserException */
    public static function convertToUtf8(string $file_content): string
    {
        // first we need to make sure to detect encoding
        // as per comment: https://github.com/php/php-src/issues/7871#issuecomment-1461983924
        $is_utf8 = mb_check_encoding($file_content, 'UTF-8');
        if ($is_utf8) {
            return $file_content;
        }

        // exception for EBU STL
        if (substr($file_content, 3, 3) === 'STL') {
            return $file_content; // ANSI encoded, but EbuStlConverter will encode result into UTF8
        }

        $encoding = mb_detect_encoding($file_content, ['ISO-8859-1', 'Windows-1252', 'UTF-16LE'], true);
        if ($encoding !== false) {
            return mb_convert_encoding($file_content, 'UTF-8', $encoding);
        }

        throw new UserException('Unknown file encoding (not utf8)');
    }

    /**
     * @param array{start: float, end: float, lines: array<string>} $block
     * @return array{start: float, end: float, lines: array<string>}
     */
    public static function shiftBlockTime(array $block, float $seconds, float $from, float $till): array
    {
        if (!static::blockTimesWithinRange($block, $from, $till)) {
            return $block;
        }

        // start
        $tmp_from_start = $block['start'] - $from;
        $start_percents = $tmp_from_start / ($till - $from);
        $block['start'] += $seconds * $start_percents;

        // end
        $tmp_from_start = $block['end'] - $from;
        $end_percents = $tmp_from_start / ($till - $from);
        $block['end'] += $seconds * $end_percents;

        return $block;
    }

    /** @param array{start: float, end: float, lines: array<string>} $block */
    public static function blockTimesWithinRange(array $block, float $from, float $till): bool
    {
        return ($from <= $block['start'] && $block['start'] <= $till && $from <= $block['end'] && $block['end'] <= $till);
    }

    public static function strContains(string $haystack, string $needle): bool {
        return strpos($haystack, $needle) !== false;
    }

    /**
     * Wraps any string to a given number of characters.
     *
     * This implementation is multi-byte aware and relies on {@link
     * http://www.php.net/manual/en/book.mbstring.php PHP's multibyte
     * string extension}.
     *
     * @see wordwrap()
     * @link https://api.drupal.org/api/drupal/core%21vendor%21zendframework%21zend-stdlib%21Zend%21Stdlib%21StringWrapper%21AbstractStringWrapper.php/function/AbstractStringWrapper%3A%3AwordWrap/8
     * @param string $string
     *   The input string.
     * @param int $width [optional]
     *   The number of characters at which <var>$string</var> will be
     *   wrapped. Defaults to <code>75</code>.
     * @param string $break [optional]
     *   The line is broken using the optional break parameter. Defaults
     *   to <code>"\n"</code>.
     * @param boolean $cut [optional]
     *   If the <var>$cut</var> is set to <code>TRUE</code>, the string is
     *   always wrapped at or before the specified <var>$width</var>. So if
     *   you have a word that is larger than the given <var>$width</var>, it
     *   is broken apart. Defaults to <code>FALSE</code>.
     * @return string
     *   Returns the given <var>$string</var> wrapped at the specified
     *   <var>$width</var>.
     */
    public static function mb_wordwrap(string $string, int $width = 75, string $break = "\n", bool $cut = false): string {
        if ($string === '') {
            return '';
        }

        if ($break === '') {
            trigger_error('Break string cannot be empty', E_USER_ERROR);
        }

        if ($width === 0 && $cut) {
            trigger_error('Cannot force cut when width is zero', E_USER_ERROR);
        }

        if (strlen($string) === mb_strlen($string)) {
            return wordwrap($string, $width, $break, $cut);
        }

        $stringWidth = mb_strlen($string);
        $breakWidth = mb_strlen($break);

        $result = '';
        $lastStart = $lastSpace = 0;

        for ($current = 0; $current < $stringWidth; $current++) {
            $char = mb_substr($string, $current, 1);

            $possibleBreak = $char;
            if ($breakWidth !== 1) {
                $possibleBreak = mb_substr($string, $current, $breakWidth);
            }

            if ($possibleBreak === $break) {
                $result .= mb_substr($string, $lastStart, $current - $lastStart + $breakWidth);
                $current += $breakWidth - 1;
                $lastStart = $lastSpace = $current + 1;
                continue;
            }

            if ($char === ' ') {
                if ($current - $lastStart >= $width) {
                    $result .= mb_substr($string, $lastStart, $current - $lastStart) . $break;
                    $lastStart = $current + 1;
                }

                $lastSpace = $current;
                continue;
            }

            if ($current - $lastStart >= $width && $cut && $lastStart >= $lastSpace) {
                $result .= mb_substr($string, $lastStart, $current - $lastStart) . $break;
                $lastStart = $lastSpace = $current;
                continue;
            }

            if ($current - $lastStart >= $width && $lastStart < $lastSpace) {
                $result .= mb_substr($string, $lastStart, $lastSpace - $lastStart) . $break;
                $lastStart = $lastSpace = $lastSpace + 1;
                continue;
            }
        }

        // @phpstan-ignore-next-line
        if ($lastStart !== $current) {
            // @phpstan-ignore-next-line
            $result .= mb_substr($string, $lastStart, $current - $lastStart);
        }

        return $result;
    }

    public static function strAfterLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + strlen($search));
    }

    public static function strBefore(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $result = strstr($subject, $search, true);

        return $result === false ? $subject : $result;
    }

    public static function removeOnlyHtmlTags(string $string): string
    {
        $letters = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
        if ($letters === false) {
            throw new \RuntimeException('some error splitting: ' . $string);
        }
        $parts = [];
        $current_text = '';
        foreach ($letters as $letter) {
            if ($letter === '<') {
                if ($current_text !== '') {
                    $parts[] = $current_text;
                    $current_text = '<';
                } else {
                    $current_text = '<';
                }
            } elseif ($letter === '>') {
                $current_text .= '>';
                $parts[] = $current_text;
                $current_text = '';
            } else {
                $current_text .= $letter;
            }
        }
        if ($current_text !== '') {
            $parts[] = $current_text;
        }

        $text = '';
        foreach ($parts as $part) {
            if (!Helpers::isRealHtmlTag($part)) {
                $text .= $part;
            }
        }
        $text = preg_replace('/\s+/', ' ', $text);
        if ($text === null) {
            throw new \RuntimeException('error: ' . $text);
        }
        return $text;
    }

    private static function isRealHtmlTag(string $tag): bool
    {
        $starts = ['div', 'p', 'a', 'b', 'i', 'u', 'strong', 'img', 'ul', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'input', 'br', 'font'];
        $attributes = ['id', 'class', 'href', 'src', 'alt', 'title', 'style', 'target', 'rel', 'type', 'color', 'size'];

        $found_start = false;
        foreach ($starts as $start) {
            if (preg_match("/^<\s*\/?\s*$start\s*\/?\s*>/i", $tag)) {
                return true;
            }

            $tag_start = Helpers::strBefore($tag, ' ');
            if ($tag_start === "<$start") {
                $found_start = true;
                break;
            }
        }
        if (!$found_start) {
            return false;
        }

        if (strpos($tag, '>') === false) {
            return false; // no closing tag
        }

        foreach ($attributes as $attribute) {
            if (preg_match("/ $attribute\s*=/i", $tag)) {
                return true;
            }
        }
        return false;
    }
}
