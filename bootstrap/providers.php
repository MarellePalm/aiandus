<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use EragLaravelPwa\EragLaravelPwaServiceProvider;
use SocialiteProviders\Manager\ServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    EragLaravelPwaServiceProvider::class,
    ServiceProvider::class,
];
