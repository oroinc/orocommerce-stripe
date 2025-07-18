<?php

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\Command\Cron;

use Oro\Bundle\CronBundle\Command\CronCommandScheduleDefinitionInterface;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsInitTopic;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\Testing\Command\CommandTestingTrait;
use Symfony\Component\Console\Command\Command;

/**
 * @dbIsolationPerTest
 */
final class ReAuthorizeCronCommandTest extends WebTestCase
{
    use CommandTestingTrait;
    use MessageQueueExtension;

    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();

        // Clear message queue before test.
        self::clearMessageCollector();
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Clear message queue after test.
        self::clearMessageCollector();
    }

    public function testCommandDefinitionAndConfiguration(): void
    {
        $command = $this->findCommand('oro:cron:stripe-payment:re-authorize');

        self::assertEquals(
            'oro:cron:stripe-payment:re-authorize',
            $command->getName()
        );
        self::assertEquals(
            'Initiates renewal of payment authorization for uncaptured payments that are about to expire.',
            $command->getDescription()
        );
        self::assertStringContainsString(
            <<<'HELP'
The <info>%command.name%</info> command initiates renewal of payment authorization for uncaptured payments
that are about to expire.
Uncaptured payments automatically expire a set number of days after creation (7 days by default).
Once expired, they are marked as refunded, and any attempt to capture them will fail.

  <info>php %command.full_name%</info>
HELP,
            $command->getHelp()
        );
    }

    public function testCommandExecutionSendsMessageToQueue(): void
    {
        $commandTester = $this->doExecuteCommand('oro:cron:stripe-payment:re-authorize');

        $output = $commandTester->getDisplay();
        self::assertStringContainsString(
            'Initiated Stripe payment authorization renewal for uncaptured payments',
            $output
        );

        self::assertMessageSent(
            ReAuthorizePaymentTransactionsInitTopic::getName(),
            []
        );

        self::assertMessagesCount(ReAuthorizePaymentTransactionsInitTopic::getName(), 1);

        self::assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testCommandImplementsCronScheduleInterface(): void
    {
        $command = $this->findCommand('oro:cron:stripe-payment:re-authorize');

        self::assertInstanceOf(CronCommandScheduleDefinitionInterface::class, $command);

        self::assertEquals('0 */1 * * *', $command->getDefaultDefinition());
    }
}
