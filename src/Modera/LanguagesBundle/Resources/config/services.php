<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Modera\LanguagesBundle\EventListener\LanguageListener;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->private()
            ->autowire()
            ->autoconfigure()
    ;

    $services->set(LanguageListener::class);
};
