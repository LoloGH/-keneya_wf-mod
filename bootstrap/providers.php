<?php

use App\Providers\AppServiceProvider;
use App\Providers\DmeIntegrationServiceProvider;

return [
    AppServiceProvider::class,
    // Enregistre apres celui du package keneya/dme : c'est ce qui lui permet
    // de remplacer les liaisons de repli du module (v3.3.0).
    DmeIntegrationServiceProvider::class,
];
