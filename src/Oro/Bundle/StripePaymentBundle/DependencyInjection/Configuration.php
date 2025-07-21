<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const string ROOT_NODE = 'oro_stripe_payment';

    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_NODE);

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('payment_method_types')
                    ->example(['card' => ['manual_capture' => true]])
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('manual_capture')
                                ->isRequired()
                                ->info(
                                    'Indicates whether this payment method type supports manual capture. '
                                    . 'Supported payment method types for manual capture are listed '
                                    . 'in the Stripe documentation: '
                                    . 'https://docs.stripe.com/payments/payment-methods/payment-method-support'
                                )
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('charge_amount')
                    ->info(
                        'Minimum and maximum charge amounts are listed in the Stripe documentation: '
                        . 'https://docs.stripe.com/currencies#minimum-and-maximum-charge-amounts'
                    )
                    ->children()
                        ->arrayNode('minimum')
                            ->info('Minimum allowed charge amounts per currency. Use "*" to match any currency.')
                            ->example(['USD' => 0.5])
                            ->useAttributeAsKey('currency')
                            ->floatPrototype()->end()
                        ->end()
                        ->arrayNode('maximum')
                            ->info('Maximum allowed charge amounts per currency. Use "*" to match any currency.')
                            ->example(['USD' => 999999.99])
                            ->useAttributeAsKey('currency')
                            ->floatPrototype()->end()
                        ->end()
                        ->arrayNode('decimal_places')
                            ->info(
                                'Configures charge amount conversion for currencies with special decimal rules. '
                                . 'See https://docs.stripe.com/currencies#zero-decimal and '
                                . 'https://docs.stripe.com/currencies#special-cases'
                            )
                            ->example([
                                'BIF' => 0,
                                'HUF' => 2,
                                'ISK' => ['decimal_places' => 2, 'fractionless' => true],
                            ])
                            ->useAttributeAsKey('currency')
                            ->arrayPrototype()
                                ->beforeNormalization()
                                    ->always(static fn ($v) => is_scalar($v) ? ['decimal_places' => $v] : $v)
                                ->end()
                                ->children()
                                    ->integerNode('decimal_places')
                                        ->info(
                                            'Number of decimal places to round to before converting '
                                            . 'to the currency’s minor unit. '
                                            . 'See https://docs.stripe.com/currencies#minor-units'
                                        )
                                        ->min(0)
                                        ->max(4)
                                        ->defaultValue(2)
                                    ->end()
                                    ->booleanNode('fractionless')
                                        ->info(
                                            'Rounds the charge amount to 0 precision before converting '
                                            . 'to the currency’s minor unit. '
                                            . 'See https://docs.stripe.com/currencies#special-cases'
                                        )
                                        ->defaultFalse()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
