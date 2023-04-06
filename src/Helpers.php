<?php

declare(strict_types=1);

namespace Circlical\Subtitles;

use Circlical\Subtitles\Providers\ConverterInterface;
use Exception;

use function class_exists;
use function end;
use function explode;
use function pack;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strtolower;
use function ucfirst;

class Helpers
{
    public static function shouldBlockTimeBeShifted(float $from, ?float $till, float $blockStart, float $blockEnd): bool
    {
        if ($blockEnd < $from) {
            return false;
        }

        if ($till === null) {
            return true;
        }

        return $till >= $blockStart;
    }

    public static function removeUtf8Bom(string $text): string
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);

        return $text;
    }

    public static function getConverter(string $extension): ConverterInterface
    {
        $className = sprintf(
            '\\Circlical\\Subtitles\\Converters\\%sConverter',
            ucfirst($extension)
        );

        if (!class_exists($className)) {
            throw new Exception("No converter exists for extension type $extension");
        }

        return new $className();
    }

    public static function fileExtension(string $filename): string
    {
        $parts = explode('.', $filename);
        $extension = end($parts);
        $extension = strtolower($extension);

        return $extension;
    }

    public static function normalizeNewLines(string $fileContent): string
    {
        $fileContent = str_replace("\r\n", "\n", $fileContent);
        $fileContent = str_replace("\r", "\n", $fileContent);

        return $fileContent;
    }

    public static function shiftBlockTime(array $block, int $seconds, float $from, float $till): array
    {
        if (!static::blockTimesWithinRange($block, $from, $till)) {
            return $block;
        }

        // start
        $tmpFromStart = $block['start'] - $from;
        $startPercents = $tmpFromStart / ($till - $from);
        $block['start'] += $seconds * $startPercents;

        // end
        $tmpFromStart = $block['end'] - $from;
        $endPercents = $tmpFromStart / ($till - $from);
        $block['end'] += $seconds * $endPercents;

        return $block;
    }

    public static function blockTimesWithinRange(array $block, float $from, float $till): bool
    {
        return $from <= $block['start'] && $block['start'] <= $till && $from <= $block['end'] && $block['end'] <= $till;
    }
}
