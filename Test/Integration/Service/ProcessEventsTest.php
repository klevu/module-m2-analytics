<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Analytics\Test\Integration\Service;

use Klevu\Analytics\Service\ProcessEvents;
use Klevu\AnalyticsApi\Api\Data\ProcessEventsResultInterface;
use Klevu\AnalyticsApi\Api\EventsDataProviderInterface;
use Klevu\AnalyticsApi\Api\ProcessEventsServiceInterface;
use Klevu\AnalyticsApi\Model\Source\ProcessEventsResultStatuses;
use Klevu\PhpSDK\Exception\ValidationException as PhpSDKValidationException;
use Klevu\Pipelines\Exception\ExtractionException;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelineConfigurationException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Exception\ValidationException as PipelinesValidationException;
use Klevu\Pipelines\Model\PipelineResult;
use Klevu\Pipelines\Pipeline\Context;
use Klevu\Pipelines\Pipeline\PipelineBuilderInterface;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\PlatformPipelines\Api\PipelineContextProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProcessEventsTest extends TestCase
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

    public function testInstanceOfInterface(): void
    {
        /** @var ProcessEvents $processEventsService */
        $processEventsService = $this->objectManager->create(ProcessEvents::class, [
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationFilepath' => __DIR__ . '/fixtures/pipeline/process-events.yml',
        ]);

        $this->assertInstanceOf(
            ProcessEventsServiceInterface::class,
            $processEventsService,
        );
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testConstruct_InvalidPipelineContextProviders(): array
    {
        return [
            [
                'foo',
            ],
            [
                ['foo'],
            ],
            [
                (object)['foo'],
            ],
            [
                [(object)['foo']],
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testConstruct_InvalidPipelineContextProviders
     */
    public function testConstruct_InvalidPipelineContextProviders(
        mixed $pipelineContextProviders,
    ): void {
        $this->expectException(RuntimeException::class);

        $this->objectManager->create(ProcessEvents::class, [
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineContextProviders' => $pipelineContextProviders,
            'pipelineConfigurationFilepath' => __DIR__ . '/fixtures/pipeline/process-events.yml',
        ]);
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testConstruct_InvalidPipelineConfigurationFilepath(): array
    {
        return [
            [
                ['foo'],
            ],
            [
                (object)['foo'],
            ],
            [
                [(object)['foo']],
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testConstruct_InvalidPipelineConfigurationFilepath
     */
    public function testConstruct_InvalidPipelineConfigurationFilepath(
        mixed $pipelineConfigurationFilepath,
    ): void {
        $this->expectException(RuntimeException::class);

        $this->objectManager->create(ProcessEvents::class, [
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationFilepath' => $pipelineConfigurationFilepath,
        ]);
    }

    public function testConstruct_PipelineConfigurationFilepathNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->objectManager->create(ProcessEvents::class, [
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationFilepath' => '/foo/bar',
        ]);
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testConstruct_InvalidPipelineConfigurationOverrideFilepaths(): array
    {
        return [
            [
                'foo',
            ],
            [
                (object)['foo'],
            ],
            [
                [(object)['foo']],
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testConstruct_InvalidPipelineConfigurationOverrideFilepaths
     */
    public function testConstruct_InvalidPipelineConfigurationOverrideFilepaths(
        mixed $pipelineConfigurationOverrideFilepaths,
    ): void {
        $this->expectException(RuntimeException::class);

        $this->objectManager->create(ProcessEvents::class, [
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationFilepath' => __DIR__ . '/fixtures/pipeline/process-events.yml',
            'pipelineConfigurationOverrideFilepaths' => $pipelineConfigurationOverrideFilepaths,
        ]);
    }

    public function testExecute_HandleInvalidPipelineConfiguration(): void
    {
        /** @var ProcessEvents $processEventsService */
        $processEventsService = $this->objectManager->create(ProcessEvents::class, [
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationFilepath' => __DIR__ . '/fixtures/pipeline/process-events.invalid.yml',
        ]);

        $this->expectException(InvalidPipelineConfigurationException::class);
        $processEventsService->execute(
            searchCriteria: null,
            via: 'PHPUnit',
        );
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testExecute_HandlePipelineExecutionFailure(): array
    {
        return [
            [
                new PhpSDKValidationException(
                    errors: ['Error Message'],
                    message: 'Test',
                ),
            ],
            [
                new ExtractionException(
                    message: 'Test',
                ),
            ],
            [
                new PipelinesValidationException(
                    validatorName: '',
                    errors: ['Error Message'],
                    data: [],
                    message: 'Test',
                ),
            ],
            [
                new TransformationException(
                    transformerName: '',
                    errors: ['Error Message'],
                    message: 'Test',
                ),
            ],
            [
                new LocalizedException(
                    phrase: __('Test'),
                ),
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testExecute_HandlePipelineExecutionFailure
     */
    public function testExecute_HandlePipelineExecutionFailure(
        \Throwable $exception,
    ): void {
        $mockPipeline = $this->getMockPipeline();
        $mockPipeline->method('execute')
            ->willThrowException($exception);

        $mockPipelineBuilder = $this->getMockPipelineBuilder(
            pipeline: $mockPipeline,
        );

        /** @var ProcessEvents $processEventsService */
        $processEventsService = $this->objectManager->create(ProcessEvents::class, [
            'pipelineBuilder' => $mockPipelineBuilder,
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationFilepath' => __DIR__ . '/fixtures/pipeline/process-events.yml',
        ]);

        $result = $processEventsService->execute(
            searchCriteria: null,
            via: 'PHPUnit',
        );

        $this->assertInstanceOf(ProcessEventsResultInterface::class, $result);
        $this->assertSame(ProcessEventsResultStatuses::ERROR, $result->getStatus());
        $this->assertContains((string)$exception->getMessage(), $result->getMessages());
        $this->assertNull($result->getPipelineResult());
    }

    public function testExecute_Success(): void
    {
        $mockPipeline = $this->getMockPipeline();
        $mockPipeline->method('execute')
            ->with(
                [
                    'foo',
                    'bar',
                    'baz',
                ],
                new Context([
                    'test' => [
                        'wom' => 'bat',
                    ],
                    'system' => [
                        'via' => 'PHPUnit',
                    ],
                ]),
            )->willReturn(new PipelineResult(
                success: true,
                payload: [
                    'foo',
                    'bar',
                    'baz',
                ],
            ));

        $mockPipelineBuilder = $this->getMockPipelineBuilder(
            pipeline: $mockPipeline,
        );

        /** @var ProcessEvents $processEventsService */
        $processEventsService = $this->objectManager->create(ProcessEvents::class, [
            'pipelineBuilder' => $mockPipelineBuilder,
            'eventsDataProvider' => $this->getMockEventsDataProvider([
                'foo',
                'bar',
                'baz',
            ]),
            'pipelineContextProviders' => [
                'test' => $this->getMockPipelineContextProvider(['wom' => 'bat']),
            ],
            'pipelineConfigurationFilepath' => __DIR__ . '/fixtures/pipeline/process-events.yml',
        ]);

        $result = $processEventsService->execute(
            searchCriteria: null,
            via: 'PHPUnit',
        );

        $this->assertInstanceOf(ProcessEventsResultInterface::class, $result);
        $this->assertSame(ProcessEventsResultStatuses::SUCCESS, $result->getStatus());
        $this->assertInstanceOf(PipelineResult::class, $result->getPipelineResult());
    }

    /**
     * @param PipelineInterface|null $pipeline
     * @return MockObject&PipelineBuilderInterface
     */
    private function getMockPipelineBuilder(?PipelineInterface $pipeline): MockObject&PipelineBuilderInterface
    {
        $mockPipelineBuilder = $this->getMockBuilder(PipelineBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        if ($pipeline) {
            $mockPipelineBuilder->method('build')
                ->willReturn($pipeline);
            $mockPipelineBuilder->method('buildFromFiles')
                ->willReturn($pipeline);
        }

        return $mockPipelineBuilder;
    }

    /**
     * @return MockObject&PipelineInterface
     */
    private function getMockPipeline(): MockObject&PipelineInterface
    {
        return $this->getMockBuilder(PipelineInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @param array<int|string, mixed>|\Generator<mixed>|null $eventsData
     * @return MockObject&EventsDataProviderInterface
     */
    private function getMockEventsDataProvider(
        array|\Generator|null $eventsData = null,
    ): MockObject&EventsDataProviderInterface {
        $mockEventsDataProvider = $this->getMockBuilder(EventsDataProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockEventsDataProvider->method('get')
            ->willReturn($eventsData);

        return $mockEventsDataProvider;
    }

    /**
     * @param mixed[]|object $pipelineContext
     * @return MockObject&PipelineContextProviderInterface
     */
    private function getMockPipelineContextProvider(
        array|object $pipelineContext,
    ): MockObject&PipelineContextProviderInterface {
        $pipelineContextProvider = $this->getMockBuilder(PipelineContextProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $pipelineContextProvider->method('get')
            ->willReturn($pipelineContext);

        return $pipelineContextProvider;
    }
}
