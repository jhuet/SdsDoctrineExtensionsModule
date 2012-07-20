<?php
/**
 * @link       http://superdweebie.com
 * @package    Sds
 * @license    MIT
 */
namespace Sds\DoctrineExtensionsModule;

use Doctrine\Common\Annotations;
use Sds\DoctrineExtensions\Manifest;
use Sds\DoctrineExtensions\ManifestConfig;
use Zend\EventManager\Event;
use Zend\ModuleManager\ModuleEvent;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\MvcEvent;

/**
 *
 * @since   1.0
 * @author  Tim Roediger <superdweebie@gmail.com>
 */
class Module
{

    protected $manifest;

    public function init(ModuleManager $moduleManager) {
        $eventManager = $moduleManager->getEventManager();
        $eventManager->attach(ModuleEvent::EVENT_LOAD_MODULES_POST, array($this, 'onLoadModulesPost'));
    }

    /**
     *
     * @param \Zend\EventManager\Event $event
     */
    public function onBootstrap(MvcEvent $event)
    {
        $app = $event->getTarget();
        $sharedManager = $app->getEventManager()->getSharedManager();

        // Attach to helper set event and load the document manager helper.
        $sharedManager->attach('doctrine', 'loadCli.post', array($this, 'loadCli'));
    }

    /**
     *
     * @param \Zend\EventManager\Event $event
     */
    public function loadCli(Event $event){
        $cli = $event->getTarget();
        $cli->addCommands($this->manifest->getCliCommands());

        $helperSet = $cli->getHelperSet();
        foreach ($this->manifest->getCliHelpers() as $key => $helper) {
            $helperSet->set($helper, $key);
        }
    }

    /**
     *
     * @param \Zend\EventManager\Event $event
     */
    public function onLoadModulesPost(ModuleEvent $event) {

        $serviceLocator = $event->getParam('ServiceManager');
        $config = $serviceLocator->get('configuration');
        $doctrineConfig = $config['doctrine'];
        $extensionsConfig = $config['sds']['doctrineExtensions'];

        if (isset($doctrineConfig[$extensionsConfig['doctrine']['configuration']]['metadataCache'])){
            $cacheName = 'doctrine.cache'.$doctrineConfig[$extensionsConfig['doctrine']['configuration']]['metadataCache'];
        } else {
            $cacheName = 'doctrine.cache.array';
        }

        $reader = new Annotations\AnnotationReader;
        $reader = new Annotations\CachedReader(
            new Annotations\IndexedReader($reader),
            $serviceLocator->get($cacheName)
        );

        $manifestConfig = array(
            'AnnotationReader' => $reader,
            'ExtensionConfigs' => $extensionsConfig['extensionConfigs']
        );

        if (isset($extensionsConfig['activeUser'])) {
            if (is_string($extensionsConfig['activeUser'])) {
                $manifestConfig['activeUser'] = $serviceLocator->get($extensionsConfig['activeUser']);
            } else {
                $manifestConfig['activeUser'] = $extensionsConfig['activeUser'];
            }
        }

        $manifest = new Manifest(new ManifestConfig($manifestConfig));
        $this->manifest = $manifest;

        //Inject subscribers
        foreach ($manifest->getSubscribers() as $subscriber) {
            $doctrineConfig['eventmanager'][$extensionsConfig['doctrine']['eventmanager']]['subscribers'][] = $subscriber;
        }

        //Inject annotations
        foreach ($manifest->getAnnotations() as $namespace => $path) {
            $doctrineConfig['configuration'][$extensionsConfig['doctrine']['configuration']]['annotations'][$namespace] = $path;
        }

        //Inject filters
        foreach ($manifest->getFilters() as $filter) {
            $doctrineConfig['configuration'][$extensionsConfig['doctrine']['configuration']]['filters'][] = $filter;
        }

        //inject document paths
        $id = 0;
        foreach ($manifest->getDocuments() as $namespace => $path) {
            $name = 'sds.doctrineExtensions.'.$id;
            $doctrineConfig['driver'][$extensionsConfig['doctrine']['driver']]['drivers'][$namespace] = $name;
            $doctrineConfig['driver'][$name] = array(
                'class' => 'Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver',                
                'paths' => array($path)
            );
            $id++;
        }

        $config['doctrine'] = $doctrineConfig;

        $allowOverride = $serviceLocator->getAllowOverride();
        $serviceLocator->setAllowOverride(true);
        $serviceLocator->setService('Configuration', $config);
        $serviceLocator->setAllowOverride($allowOverride);
    }

    public function getConfig()
    {
        return include __DIR__ . '/../../../config/module.config.php';
    }
}