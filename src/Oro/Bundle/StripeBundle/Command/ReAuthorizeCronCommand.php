<?php

namespace Oro\Bundle\StripeBundle\Command;

use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use Oro\Bundle\StripeBundle\Handler\ReAuthorizationHandler;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * According to STRIPE rules authorized amounts should be unblocked after 7 days.
 * To handle this case we need to re-authorize almost expired authorize transactions.
 */
class ReAuthorizeCronCommand extends Command implements CronCommandInterface
{
    protected static $defaultName = 'oro:cron:stripe:re-authorize';

    private EntitiesTransactionsProvider $transactionsProvider;
    private ReAuthorizationHandler $reAuthorizationHandler;

    public function __construct(
        EntitiesTransactionsProvider $transactionsProvider,
        ReAuthorizationHandler $reAuthorizationHandler
    ) {
        parent::__construct();
        $this->transactionsProvider = $transactionsProvider;
        $this->reAuthorizationHandler = $reAuthorizationHandler;
    }

    public function isActive()
    {
        return $this->transactionsProvider->hasExpiringAuthorizationTransactions();
    }

    public function getDefaultDefinition()
    {
        // At minute 0 past every hour.
        return '0 */1 * * *';
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Starting STRIPE re-authorization');
        $this->reAuthorizationHandler->reAuthorize();
        $output->writeln('STRIPE re-authorization finished');

        return self::SUCCESS;
    }
}
