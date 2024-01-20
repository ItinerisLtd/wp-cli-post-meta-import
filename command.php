<?php

declare(strict_types=1);

namespace ItinerisLtd\PostMetaImport;

use WP_CLI;

if (! class_exists('\WP_CLI')) {
    return;
}

$autoloader = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoloader)) {
    include_once $autoloader;
}

WP_CLI::add_command('post meta import', PostMetaImport::class);
