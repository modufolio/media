<?php

declare(strict_types = 1);

// MediaModel resolves master files against the consuming app's BASE_DIR (a
// known wart — see the README); tests point it at a throwaway directory.
if (!defined('BASE_DIR')) {
    define('BASE_DIR', sys_get_temp_dir() . '/modufolio-media-tests-' . getmypid());
}

if (!is_dir(BASE_DIR)) {
    mkdir(BASE_DIR, 0o755, true);
}

require dirname(__DIR__) . '/vendor/autoload.php';
