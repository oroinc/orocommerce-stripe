<?php

namespace Oro\Bundle\StripeBundle\Command;

use Oro\Bundle\CronBundle\Command\CronCommandActivationInterface;
use Oro\Bundle\CronBundle\Command\CronCommandScheduleDefinitionInterface;
use Oro\Bundle\StripeBundle\Handler\ReAuthorizationHandler;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * According to STRIPE rules authorized amounts should be unblocked after 7 days.
 * To handle this case we need to re-authorize almost expired authorize transactions.
 */
class ReAuthorizeCronCommand extends Command implements
    CronCommandScheduleDefinitionInterface,
    CronCommandActivationInterface
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

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function configure()
    {
        $this->setDescription('Cancels expiring Stripe authorization holds and places new ones instead.')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command cancels expiring Stripe authorization holds and places new ones instead 
(payment card authorizations expire in Stripe after 7 days by default).

  <info>php %command.full_name%</info>

HELP
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Starting Stripe authorizations processing');
        $this->reAuthorizationHandler->reAuthorize();
        $output->writeln('Stripe re-authorizations processing finished');

        return self::SUCCESS;
    }
}
