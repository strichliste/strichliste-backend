<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    // PHP version sets only (derived from composer.json ^8.4) — no opinionated sets
    ->withPhpSets();
