<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Command;

use Oro\Bundle\StripeBundle\Command\ReAuthorizeCronCommand;
use Oro\Bundle\StripeBundle\Handler\ReAuthorizationHandler;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReAuthorizeCronCommandTest extends TestCase
{
    private EntitiesTransactionsProvider|MockObject $transactionsProvider;
    private ReAuthorizationHandler|MockObject $reAuthorizationHandler;

    private ReAuthorizeCronCommand $command;

    protected function setUp(): void
    {
        $this->transactionsProvider = $this->createMock(EntitiesTransactionsProvider::class);
        $this->reAuthorizationHandler = $this->createMock(ReAuthorizationHandler::class);

        $this->command = new ReAuthorizeCronCommand(
            $this->transactionsProvider,
            $this->reAuthorizationHandler
        );
    }

    public function testIsActive()
    {
        $this->transactionsProvider->expects($this->once())
            ->method('hasExpiringAuthorizationTransactions')
            ->willReturn(true);

        $this->assertTrue($this->command->isActive());
    }

    public function testExecute()
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $this->reAuthorizationHandler->expects($this->once())
            ->method('reAuthorize');

        $this->assertEquals($this->command::SUCCESS, $this->command->run($input, $output));
    }
}
