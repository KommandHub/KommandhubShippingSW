<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests;

$loader = (new TestBootstrapper())
    ->setPlatformEmbedded(true)
    ->addCallingPlugin()
    ->setForceInstallPlugins(true)
    ->addActivePlugins(
        'KommandhubShippingSW',
    )
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('Kommandhub\ShippingSW\\Tests\\', __DIR__);
