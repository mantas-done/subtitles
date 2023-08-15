<?php

// Name of the PHAR archive
$pharFile = '../bin/subtitles.phar';
if (file_exists($pharFile)) {
    unlink($pharFile);
}

// Create a new PHAR archive
$phar = new Phar($pharFile);

// Add files to the archive
$includes = [];
$includes[] = '../src/Code/';
$includes[] = '../src/cli.php';
$includes[] = '../src/Subtitles.php';
$includes[] = '../vendor/autoload.php';
$includes[] = '../vendor/composer/';
$includes_string = implode('|', $includes);
$includes_string = str_replace('.', '\.', $includes_string);
$includes_string = str_replace('/', '\\/', $includes_string);
$includes_string = "/$includes_string/";
$phar->buildFromDirectory(__DIR__ . '/../', $includes_string);

// Set the default entry point (your CLI script)
$phar->setDefaultStub('/src/cli.php');

// Make the PHAR archive executable
//chmod($pharFile, 0755);

echo "PHAR archive created: $pharFile\n";