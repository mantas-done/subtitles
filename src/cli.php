<?php

require_once '../vendor/autoload.php';

if (!isset($argv[1])) {
    die('No input file');
}
if (!isset($argv[2])) {
    die('No output file');
}
\Done\Subtitles\Subtitles::convert($argv[1], $argv[2]);