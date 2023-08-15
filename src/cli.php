<?php

require_once '../vendor/autoload.php';

if (!isset($argv[1])) {
    die('No input file');
}
if (!isset($argv[2])) {
    die('No output file');
}

try {
    \Done\Subtitles\Subtitles::convert(realpath($argv[1]), $argv[2]);
} catch (\Done\Subtitles\Code\UserException $e) {
    echo 'Error: ' . $e->getMessage();
    die(2);
} catch (Exception $e) {
    echo 'Exception: ' . $e->getMessage();
    die(1);
}
