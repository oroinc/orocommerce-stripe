<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\AddressBundle\Entity\AbstractAddress;
use Oro\Bundle\AddressBundle\Entity\AddressType;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;

/**
 * Prepare data for create customer request.
 */
class CreateCustomerRequest extends StripeApiRequestAbstract
{
    private DoctrineHelper $doctrineHelper;
    private EntityNameResolver $entityNameResolver;

    public function __construct(
        PaymentTransaction $paymentTransaction,
        DoctrineHelper $doctrineHelper,
        EntityNameResolver $entityNameResolver
    ) {
        parent::__construct($paymentTransaction);

        $this->doctrineHelper = $doctrineHelper;
        $this->entityNameResolver = $entityNameResolver;
    }

    #[\Override]
    public function getRequestData(): array
    {
        $requestData = [
            'payment_method' => $this->getPaymentMethodId()
        ];

        $this->fillAddress($requestData);
        $this->fillCustomerUserDetails($requestData);

        return $requestData;
    }

    private function getSourceEntity(PaymentTransaction $paymentTransaction)
    {
        return $this->doctrineHelper->getEntityReference(
            $paymentTransaction->getEntityClass(),
            $paymentTransaction->getEntityIdentifier()
        );
    }

    #[\Override]
    public function getPaymentId(): ?string
    {
        return null;
    }

    private function fillAddress(array &$requestData): void
    {
        $sourceEntity = $this->getSourceEntity($this->getTransaction());

        $address = null;
        if (method_exists($sourceEntity, 'getBillingAddress')) {
            $address = $sourceEntity->getBillingAddress();
        } elseif (method_exists($sourceEntity, 'getAddressByTypeName')) {
            $address = $sourceEntity->getAddressByTypeName(AddressType::TYPE_BILLING);
        }

        if (!$address instanceof AbstractAddress) {
            return;
        }

        $requestData['address'] = [
            'city' => $address->getCity(),
            'country' => $address->getCountryIso2(),
            'line1' => $address->getStreet(),
            'postal_code' => $address->getPostalCode(),
            'state' => $address->getRegionName()
        ];
    }

    private function fillCustomerUserDetails(array &$requestData): void
    {
        $customerUser = $this->getTransaction()->getFrontendOwner();
        if (!$customerUser) {
            return;
        }
        $requestData['email'] = $customerUser->getEmail();
        $requestData['name'] = $this->entityNameResolver->getName($customerUser);
    }
}
