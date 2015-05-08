<?php

return array(

    // Disable logging
    'Psr\Log\LoggerInterface' => DI\object('Psr\Log\NullLogger'),

    'Piwik\Cache\Backend' => function (\Interop\Container\ContainerInterface $c) {
        return \Piwik\Cache::buildBackend($c, 'file');
    },
    'cache.eager.cache_id' => 'eagercache-test-',

    // Disable loading core translations
    'Piwik\Translation\Translator' => DI\object()
        ->constructorParameter('directories', array()),

);
