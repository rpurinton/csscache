<?php

use RPurinton\CssCache;

require_once __DIR__ . '/vendor/autoload.php';

$dir = __DIR__ . '/testCssCache';

@unlink("$dir/style.cache");

CssCache::compile($dir);

if (file_exists("$dir/style.cache")) {
    echo ("\nSuccess!\n");
} else {
    echo "\nError: CSS cache not found\n";
}

@unlink("$dir/style.cache");
