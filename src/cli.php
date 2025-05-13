<?php

require_once '../vendor/autoload.php';

if (!isset($argv[1])) {
    die('No input file');
}
if (!isset($argv[2])) {
    die('No output file');
}

try {
    $path = realpath($argv[1]);
    if ($path === false) {
        echo 'File not found';
        die(1);
    }
    (new \Done\Subtitles\Subtitles())->convert($path, $argv[2]);
} catch (\Done\Subtitles\Code\Exceptions\UserException $e) {
    echo 'Error: ' . $e->getMessage();
    die(2);
} catch (Exception $e) {
    echo 'Exception: ' . $e->getMessage();
    die(1);
}
