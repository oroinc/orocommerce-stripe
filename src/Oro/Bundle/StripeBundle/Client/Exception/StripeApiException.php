<?php

namespace Oro\Bundle\StripeBundle\Client\Exception;

/**
 * Exception class used to wrap up exceptions thrown by Stripe API.
 */
class StripeApiException extends \Exception
{
    private ?string $declineCode;
    private ?string $stripeErrorCode;

    public function __construct(?string $message = '', ?string $stripeErrorCode = '', ?string $declineCode = '')
    {
        $this->declineCode = $declineCode;
        $this->stripeErrorCode = $stripeErrorCode;
        parent::__construct($message);
    }

    public function getDeclineCode(): ?string
    {
        return $this->declineCode;
    }

    public function getStripeErrorCode(): ?string
    {
        return $this->stripeErrorCode;
    }
}
