<?php

$paths = [
    dirname(__DIR__, 1) . "/vendor/autoload.php",
    dirname(__DIR__, 4) . "/vendor/autoload.php",
];

$autoloadPath = null;

foreach ($paths as $path) {
    if (file_exists($path)) {
        $autoloadPath = $path;
        break;
    }
}

if (!isset($autoloadPath)) {
    fwrite(STDERR, "Could not locate autoload.php");
    exit(1);
}

require $autoloadPath;
