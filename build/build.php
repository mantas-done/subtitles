<?php

// Name of the PHAR archive
$pharFile = 'my-package.phar';

// Create a new PHAR archive
$phar = new Phar($pharFile);

// Add files to the archive
$phar->buildFromDirectory(__DIR__ . '/../');

// Set the default entry point (your CLI script)
$phar->setDefaultStub(__DIR__ . '/src/cli.php');

// Make the PHAR archive executable
//chmod($pharFile, 0755);

echo "PHAR archive created: $pharFile\n";