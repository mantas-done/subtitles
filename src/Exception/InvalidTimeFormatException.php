<?php

declare(strict_types=1);

namespace Circlical\Subtitles\Exception;

use Exception;

class InvalidTimeFormatException extends Exception
{
    public function __construct(string $badFormat)
    {
        parent::__construct("An invalid subtitle time format was received: " . $badFormat);
    }
}
