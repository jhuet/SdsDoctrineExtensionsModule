<?php
return [
    'sds' => [
        'doctrineExtensions' => [
            'doctrine' => [
                'driver' => 'odm_default',
                'eventmanager' => 'odm_default',
                'configuration' => 'odm_default',
                'documentManager' => 'doctrine.documentmanager.odm_default',
            ],
            'extensionConfigs' => [
//                'Sds\DoctrineExtensions\AccessControl' => true,
//                'Sds\DoctrineExtensions\Accessor' => true,
//                'Sds\DoctrineExtensions\Annotation' => true,
//                'Sds\DoctrineExtensions\Audit' => true,
//                'Sds\DoctrineExtensions\Crypt' => true,
//                'Sds\DoctrineExtensions\Dojo' => [
//                    'destPaths' => [
//                        'all' => [
//                            'filter' => '',
//                            'path' => 'public/js/dojo_src'
//                        ],
//                    ],
//                ],
//                'Sds\DoctrineExtensions\DoNotHardDelete' => true,
//                'Sds\DoctrineExtensions\Freeze' => true,
//                'Sds\DoctrineExtensions\Readonly' => true,
//                'Sds\DoctrineExtensions\Rest' => true,
//                'Sds\DoctrineExtensions\Serializer' => true,
//                'Sds\DoctrineExtensions\SoftDelete' => true,
//                'Sds\DoctrineExtensions\Stamp' => true,
//                'Sds\DoctrineExtensions\State' => true,
//                'Sds\DoctrineExtensions\UiHints' => true,
//                'Sds\DoctrineExtensions\Validator' => true,
//                'Sds\DoctrineExtensions\Workflow' => true,
//                'Sds\DoctrineExtensions\Zone' => true,
            ],
        ],
    ],

    'service_manager' => [
        'invokables' => [
            'Sds\DoctrineExtensions\DocumentValidator' => 'Sds\DoctrineExtensions\Validator\DocumentValidator',
        ],
        'factories' => [
            'Sds\DoctrineExtensions\Serializer' => 'Sds\DoctrineExtensionsModule\Service\SerializerFactory',
        ],
    ],

    'controllers' => [
        'factories' => [
            'Sds\DoctrineExtensionsModule\Controller\JsonRestfulController' => function($serviceLocator){
                return new Sds\DoctrineExtensionsModule\Controller\JsonRestfulController(
                    ['restEndpoint' => $serviceLocator->getServiceLocator()->get('application')->getMvcEvent()->getRouteMatch()->getParam('restEndpoint')]
                );
            },
        ],
    ],

    'router' => [
        'routes' => [
            // Rest api route
//            'Sds\Zf2ExtensionsModule\RestRoute' => [
//                'options' => [
//                    'defaults' => [
//                        'controller' => 'Sds\DoctrineExtensionsModule\Controller\JsonRestfulController'
//                    ],
//                ],
//            ],
        ],
    ],
];