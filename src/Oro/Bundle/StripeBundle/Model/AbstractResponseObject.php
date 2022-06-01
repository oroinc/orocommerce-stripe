<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Implements basic common methods for Stripe Response objects.
 */
abstract class AbstractResponseObject extends \ArrayObject
{
    /**
     * @param string $key
     * @param null $defaultValue
     * @return mixed|null
     */
    public function getValue(string $key, $defaultValue = null)
    {
        if (!$this->offsetExists($key)) {
            return $defaultValue;
        }

        return $this->offsetGet($key);
    }
}
