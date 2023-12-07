<?php

namespace Oro\Bundle\StripeBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Load bundle basic configuration files.
 */
class OroStripeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $container->prependExtensionConfig($this->getAlias(), $config);

        $loader = new YamlFileLoader($container, new FileLocator(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config'
        ));

        $loader->load('services.yml');
        $loader->load('form_types.yml');
        $loader->load('method.yml');
        $loader->load('payment_actions.yml');
        $loader->load('controllers.yml');
    }
}
