<?php

namespace Oro\Bundle\StripeBundle\EventHandler\Exception;

/**
 * Exception used to identify if event is not supported and there are no handlers to process it.
 */
class NotSupportedEventException extends \Exception
{
}
