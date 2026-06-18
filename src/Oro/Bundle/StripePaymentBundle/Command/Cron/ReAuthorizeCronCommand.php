<?php

namespace Oro\Bundle\StripePaymentBundle\Command\Cron;

use Oro\Bundle\CronBundle\Command\CronCommandActivationInterface;
use Oro\Bundle\CronBundle\Command\CronCommandScheduleDefinitionInterface;
use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsInitTopic;
use Oro\Bundle\StripePaymentBundle\Entity\Repository\StripePaymentElementSettingsRepository;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renews payment authorizations for uncaptured payments that are about to expire fo.
 */
#[AsCommand(
    name: 'oro:cron:stripe-payment:re-authorize',
    description: 'Initiates renewal of payment authorization for uncaptured payments that are about to expire.'
)]
final class ReAuthorizeCronCommand extends Command implements
    CronCommandScheduleDefinitionInterface,
    CronCommandActivationInterface
{
    private ?StripePaymentElementSettingsRepository $stripePaymentElementSettingsRepository = null;

    public function __construct(
        private readonly MessageProducerInterface $messageProducer
    ) {
        parent::__construct();
    }

    public function setStripePaymentElementSettingsRepository(
        StripePaymentElementSettingsRepository $stripePaymentElementSettingsRepository
    ): void {
        $this->stripePaymentElementSettingsRepository = $stripePaymentElementSettingsRepository;
    }

    #[\Override]
    public function getDefaultDefinition(): string
    {
        // At minute 0 past every hour.
        return '0 */1 * * *';
    }

    #[\Override]
    public function isActive(): bool
    {
        return (bool) $this->stripePaymentElementSettingsRepository?->hasEnabledSettings();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command initiates renewal of payment authorization for uncaptured payments
that are about to expire.
Uncaptured payments automatically expire a set number of days after creation (7 days by default).
Once expired, they are marked as refunded, and any attempt to capture them will fail.

  <info>php %command.full_name%</info>
HELP
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyleOutput = new SymfonyStyle($input, $output);

        $this->messageProducer->send(ReAuthorizePaymentTransactionsInitTopic::getName(), []);

        $symfonyStyleOutput->info('Initiated Stripe payment authorization renewal for uncaptured payments');

        return self::SUCCESS;
    }
}
