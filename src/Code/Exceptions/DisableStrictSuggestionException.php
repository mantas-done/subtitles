<?php

namespace Done\Subtitles\Code\Exceptions;

// this exception message is safe to show to users
// it indicates that user can disable strict mode and hope that his received file will be without errors
class DisableStrictSuggestionException extends UserException
{

}