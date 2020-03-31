<?php

namespace Masev\SettingsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\Kernel;
use Masev\SettingsBundle\Dal\ParametersStorageInterface;
use Masev\SettingsBundle\Parser;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class MasevSettingsExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Allow an extension to prepend the extension configurations.
     *
     * @param ContainerBuilder $container
     */
    public function prepend(ContainerBuilder $container)
    {
        $configs = $container->getExtensionConfig('masev_settings');

        $processor = new Processor();
        $configuration = $this->getConfiguration($configs, $container);
        $config = $processor->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        // Initialize Settings service
        $this->loadStorageEngine($config, $container);

        // Load settings schema
        $this->loadDynamicParametersSchema($config, $container);

        // Inject parameters
        $container->get('masev_settings.dependency_injection.container_injection_manager')->inject($container);
    }

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('masev_settings.varnish_purge.enabled', $config['varnish_purge']['enabled']);
        if (!empty($config['varnish_purge']['purger_interface_id'])) {
            $container->setParameter('masev_settings.varnish_purge.purger_interface_id', $config['varnish_purge']['purger_interface_id']);
        }

        $container->setParameter('masev_settings.config.form.browse_limit', $config['form']['browse_limit']);
    }

    /**
     * @param                  $config
     * @param ContainerBuilder $container
     *
     * @return ParametersStorageInterface
     * @throws \Exception
     */
    protected function loadStorageEngine($config, ContainerBuilder $container)
    {
        $parametersStorageServiceDef = $container->getDefinition('masev_settings.dal.parameters_storage');

        if (isset($config['mysql'])) {
            $this->prepareMysqlStorageEngine($config['mysql'], $container, $parametersStorageServiceDef);
        } else {
            throw new \Exception('Unsupported storage');
        }

        $parametersStorageServiceDef->addArgument($container->getParameter('masev_settings.config.storage'));
        return $container->get('masev_settings.dal.parameters_storage');
    }

    /**
     * @param $config
     * @param ContainerBuilder $container
     * @param Definition $parametersStorageServiceDef
     */
    protected function prepareMysqlStorageEngine($config, ContainerBuilder $container, Definition $parametersStorageServiceDef)
    {
        $container->setParameter('masev_settings.config.storage', array(
            'host' => $config['host'],
            'user' => $config['user'],
            'password' => $config['password'],
            'dbname' => $config['dbname']
        ));
        $parametersStorageServiceDef->setClass($container->getParameter('masev_settings.dal.mysql.class'));
    }

    /**
     * @param                  $config
     * @param ContainerBuilder $container
     *
     * @return array
     */
    protected function loadDynamicParametersSchema($config, ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        $schema = array();
        foreach ($config['bundles'] as $bundle) {
            $reflector = new \ReflectionClass($bundles[$bundle]);

            $loader = new $config['config_file_parser'](new FileLocator(dirname($reflector->getFileName()) . '/Resources/config'));
            $schema = array_merge($loader->load('settings.xml'), $schema);
        }

        $container->setParameter('masev_settings.schema', $schema);
        return $schema;
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        return new Configuration(array_keys($bundles));
    }
}
