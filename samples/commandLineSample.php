<?php
require __DIR__ . '/../vendor/autoload.php';

use Done\Subtitles\Subtitles;

Subtitles::convert('./subtitles.ttml', 'subtitles.vtt');
