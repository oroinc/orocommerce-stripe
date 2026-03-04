<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Migrations\Schema\v7_0_0_1;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\ConfigBundle\Migration\RenameConfigNameQuery;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Renames the "oro_stripe.apple_pay_domain_verification" to "oro_stripe_payment.apple_pay_domain_verification" system
 * configuration setting.
 */
class RenameApplePayDomainVerificationSystemConfig implements Migration
{
    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $queries->addQuery(
            new RenameConfigNameQuery(
                'apple_pay_domain_verification',
                'apple_pay_domain_verification',
                'oro_stripe',
                'oro_stripe_payment'
            )
        );
    }
}
