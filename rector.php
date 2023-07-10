<?php

use Rector\Config\RectorConfig;
use Utils\Rector\Rector\NamespaceMigrationRule;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(NamespaceMigrationRule::class);

    $rectorConfig->importNames();
};
