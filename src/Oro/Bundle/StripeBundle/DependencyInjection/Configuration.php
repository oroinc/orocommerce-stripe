<?php

namespace Oro\Bundle\StripeBundle\DependencyInjection;

use Oro\Bundle\ConfigBundle\DependencyInjection\SettingsBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROOT_NODE = 'oro_stripe';
    public const APPLE_PAY_DOMAIN_VERIFICATION = 'apple_pay_domain_verification';

    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_NODE);
        $rootNode = $treeBuilder->getRootNode();

        SettingsBuilder::append(
            $rootNode,
            [
                self::APPLE_PAY_DOMAIN_VERIFICATION => ['type' => 'text', 'value' => ''],
            ]
        );

        return $treeBuilder;
    }
}
