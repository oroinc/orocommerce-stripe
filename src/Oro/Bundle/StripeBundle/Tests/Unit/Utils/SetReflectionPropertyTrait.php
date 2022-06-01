<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Utils;

use ReflectionClass;

trait SetReflectionPropertyTrait
{
    /**
     * @param string $reflectionClass
     * @param object $object
     * @param string $property
     * @param mixed $value
     * @throws \ReflectionException
     */
    public function setProperty(string $reflectionClass, object $object, string $property, $value): void
    {
        $reflectionClass = new ReflectionClass($reflectionClass);
        $reflectionClass->getProperty($property)->setValue($object, $value);
    }
}
