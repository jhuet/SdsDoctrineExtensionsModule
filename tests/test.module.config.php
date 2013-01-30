<?php

return [
    'sds' => [
        'doctrineExtensions' => [
            'extensionConfigs' => [
                'Sds\DoctrineExtensions\AccessControl' => true,
                'Sds\DoctrineExtensions\Accessor' => true,
                'Sds\DoctrineExtensions\Annotation' => true,
                'Sds\DoctrineExtensions\Audit' => true,
                'Sds\DoctrineExtensions\Crypt' => true,
                'Sds\DoctrineExtensions\DoNotHardDelete' => true,
                'Sds\DoctrineExtensions\Dojo' => [
                    'destPaths' => [
                        'all' => [
                            'filter' => 'Sds\DoctrineExtensionsModule\Test\TestAsset\Document',
                            'path' => 'data'
                        ],
                    ],
                ],
                'Sds\DoctrineExtensions\Freeze' => true,
                'Sds\DoctrineExtensions\Readonly' => true,
                'Sds\DoctrineExtensions\Rest' => ['basePath' => 'http://test.com/api'],
                'Sds\DoctrineExtensions\Serializer' => true,
                'Sds\DoctrineExtensions\SoftDelete' => true,
                'Sds\DoctrineExtensions\Stamp' => true,
                'Sds\DoctrineExtensions\State' => true,
                'Sds\DoctrineExtensions\Validator' => true,
                'Sds\DoctrineExtensions\Workflow' => true,
                'Sds\DoctrineExtensions\Zone' => true,
            ],
        ],
    ],

    'doctrine' => [
        'configuration' => [
            'odm_default' => [
                'default_db' => 'doctrineExtensionsModuleTest',
                'proxy_dir'    => __DIR__ . '/Proxy',
                'hydrator_dir' => __DIR__ . '/Hydrator',
            ],
        ],
        'driver' => [
            'odm_default' => [
                'drivers' => [
                    'Sds\DoctrineExtensionsModule\Test\TestAsset\Document' => 'test'
                ],
            ],
            'test' => [
                'class' => 'Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver',
                'paths' => [
                    __DIR__.'/Sds/DoctrineExtensionsModule/Test/TestAsset/Document'
                ],
            ],
        ],
    ],

    'controllers' => [
        'factories' => [
            'Sds\DoctrineExtensionsModule\Test\TestAsset\GameController' => function(){
                return new Sds\DoctrineExtensionsModule\Controller\JsonRestfulController(
                    ['documentClass' => 'Sds\DoctrineExtensionsModule\Test\TestAsset\Document\Game']
                );
            },
            'Sds\DoctrineExtensionsModule\Test\TestAsset\RoadController' => function(){
                return new Sds\DoctrineExtensionsModule\Controller\JsonRestfulController(
                    ['documentClass' => 'Sds\DoctrineExtensionsModule\Test\TestAsset\Document\Road']
                );
            }
        ],
    ],

    'router' => [
        'routes' => [
            'Sds\Zf2ExtensionsModule\RestRoute' => [
                'type' => 'Sds\Zf2ExtensionsModule\RestRoute',
                'options' => [
                    'route' => '/api',
                    'defaults' => [
                        'controller' => 'Sds\DoctrineExtensionsModule\Controller\JsonRestfulController'
                    ],
                    'endpointToControllerMap' => [
                        'road' => 'Sds\DoctrineExtensionsModule\Test\TestAsset\RoadController'
                    ],
                ],
            ],
        ],
    ],
];
