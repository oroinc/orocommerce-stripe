<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method;

use LogicException;
use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;
use PHPUnit\Framework\TestCase;

class StripePaymentActionMapperTest extends TestCase
{
    public function testGetPaymentAction(): void
    {
        $result = StripePaymentActionMapper::getPaymentAction('manual');
        $this->assertEquals('authorize', $result);

        $result = StripePaymentActionMapper::getPaymentAction('automatic');
        $this->assertEquals('capture', $result);

        $this->expectException(LogicException::class);
        StripePaymentActionMapper::getPaymentAction('');
    }
}
