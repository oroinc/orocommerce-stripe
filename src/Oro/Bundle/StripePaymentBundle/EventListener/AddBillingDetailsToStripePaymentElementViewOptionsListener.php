<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\EventListener;

use Oro\Bundle\CustomerBundle\Entity\AddressPhoneAwareInterface;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\LocaleBundle\Model\AddressInterface;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentElementViewOptionsEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\View\StripePaymentElementMethodView;

/**
 * Adds customer user billing details to Stripe Payment Element view options.
 *
 * @link https://docs.stripe.com/js/elements_object/create_payment_element#payment_element_create-options-defaultValues
 */
final class AddBillingDetailsToStripePaymentElementViewOptionsListener
{
    public function __construct(
        private readonly TokenAccessorInterface $tokenAccessor,
        private readonly EntityNameResolver $entityNameResolver
    ) {
    }

    public function onStripePaymentElementViewOptions(StripePaymentElementViewOptionsEvent $event): void
    {
        $customerUser = $this->tokenAccessor->getUser();
        if (!$customerUser instanceof CustomerUser) {
            // Ensures only authenticated user can access sensitive data.
            return;
        }

        $stripePaymentElementOptions = $event->getViewOption(
            StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS
        );
        $billingDetails = $this->getBillingDetails($event, $customerUser);
        if (!$billingDetails) {
            return;
        }

        $stripePaymentElementOptions['defaultValues']['billingDetails'] = array_merge(
            $stripePaymentElementOptions['defaultValues']['billingDetails'] ?? [],
            $billingDetails
        );
        $event->addViewOption(
            StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS,
            $stripePaymentElementOptions
        );
    }

    /**
     * @param StripePaymentElementViewOptionsEvent $event
     *
     * @return array<string,array<string,string>|string>
     */
    private function getBillingDetails(StripePaymentElementViewOptionsEvent $event, CustomerUser $customerUser): array
    {
        $paymentContext = $event->getPaymentContext();

        $billingAddress = $paymentContext->getBillingAddress();
        $billingDetails = [
            'name' => $this->entityNameResolver->getName($customerUser),
            'email' => $customerUser->getEmail(),
        ];

        if ($billingAddress instanceof AddressPhoneAwareInterface) {
            $billingDetails['phone'] = $billingAddress->getPhone();
        }

        if ($billingAddress instanceof AddressInterface) {
            $billingDetails['address'] = [
                'line1' => $billingAddress->getStreet(),
                'line2' => $billingAddress->getStreet2(),
                'city' => $billingAddress->getCity(),
                'state' => $billingAddress->getRegionName(),
                'country' => $billingAddress->getCountryIso2(),
                'postal_code' => $billingAddress->getPostalCode(),
            ];
            $billingDetails['address'] = array_filter($billingDetails['address']);
        }

        return $billingDetails;
    }
}
