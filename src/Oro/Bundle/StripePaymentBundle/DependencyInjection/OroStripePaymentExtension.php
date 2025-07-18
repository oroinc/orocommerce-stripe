<?php

namespace Oro\Bundle\StripePaymentBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

class OroStripePaymentExtension extends Extension
{
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $container->prependExtensionConfig($this->getAlias(), $config);

        $container->setParameter('oro_stripe_payment.bundle_config', $config);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('controllers.yml');
        $loader->load('commands.yml');
        $loader->load('mq_topics.yml');
        $loader->load('mq_processors.yml');
        $loader->load('services.yml');
        $loader->load('services_stripe_api.yml');
        $loader->load('services_stripe_api_customers.yml');
        $loader->load('services_stripe_api_payment_intents.yml');
        $loader->load('services_stripe_api_webhook_endpoints.yml');
        $loader->load('services_stripe_payment_element_method.yml');

        if ('test' === $container->getParameter('kernel.environment')) {
            $loader->load('services_test.yml');
        }
    }
}
