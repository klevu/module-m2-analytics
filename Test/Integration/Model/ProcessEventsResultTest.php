<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Analytics\Test\Integration\Model;

use Klevu\Analytics\Model\ProcessEventsResult;
use Klevu\AnalyticsApi\Api\Data\ProcessEventsResultInterface;
use Klevu\AnalyticsApi\Api\Data\ProcessEventsResultInterfaceFactory;
use Klevu\AnalyticsApi\Model\Source\ProcessEventsResultStatuses;
use Klevu\Pipelines\Model\PipelineResult;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class ProcessEventsResultTest extends TestCase
{
    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();
    }

    public function testInterfaceGenerationFromFactory(): void
    {
        /** @var ProcessEventsResultInterfaceFactory $processEventsResultFactory */
        $processEventsResultFactory = $this->objectManager->get(ProcessEventsResultInterfaceFactory::class);

        $this->assertInstanceOf(
            ProcessEventsResult::class,
            $processEventsResultFactory->create(),
        );
    }

    public function testCreateFromObjectManager(): void
    {
        $this->assertInstanceOf(
            ProcessEventsResult::class,
            $this->objectManager->create(ProcessEventsResultInterface::class),
        );
    }

    public function testGettersAndSetters(): void
    {
        /** @var ProcessEventsResult $processEventsResult */
        $processEventsResult = $this->objectManager->create(ProcessEventsResult::class);

        $this->assertNull($processEventsResult->getStatus());
        $this->assertSame([], $processEventsResult->getMessages());
        $this->assertNull($processEventsResult->getPipelineResult());

        $processEventsResult->setStatus(ProcessEventsResultStatuses::SUCCESS);
        $messages = [
            'foo',
            __('bar'),
        ];
        $processEventsResult->setMessages($messages);
        $pipelineResult = $this->objectManager->create(PipelineResult::class, [
            'success' => true,
            'payload' => [
                'foo' => 'bar',
            ],
            'messages' => [],
        ]);
        $processEventsResult->setPipelineResult($pipelineResult);

        $this->assertSame(ProcessEventsResultStatuses::SUCCESS, $processEventsResult->getStatus());
        $this->assertSame(['foo', 'bar'], $processEventsResult->getMessages());
        $this->assertSame($pipelineResult, $processEventsResult->getPipelineResult());
    }
}
