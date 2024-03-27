<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Test\Unit\Service;

use Klevu\Analytics\Exception\InvalidStatusTransitionException;
use Klevu\Analytics\Model\ProcessEventsResult;
use Klevu\Analytics\Service\Action\ParseFilepathActionInterface;
use Klevu\Analytics\Service\ProcessEvents;
use Klevu\AnalyticsApi\Api\Data\ProcessEventsResultInterfaceFactory;
use Klevu\AnalyticsApi\Api\EventsDataProviderInterface;
use Klevu\AnalyticsApi\Model\Source\ProcessEventsResultStatuses;
use Klevu\PhpSDK\Exception\ValidationException as PhpSDKValidationException;
use Klevu\Pipelines\Exception\ExtractionException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Exception\ValidationException as PipelinesValidationException;
use Klevu\Pipelines\Pipeline\Context;
use Klevu\Pipelines\Pipeline\ContextFactory as PipelineContextFactory;
use Klevu\Pipelines\Pipeline\PipelineBuilderInterface;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProcessEventsTest extends TestCase
{
    public function testExecute_PipelineCompletesSuccess(): void
    {
        $mockPipeline = $this->getMockPipelineWithResult(
            pipelineResult: [
                [
                    'orderId' => 12345,
                    'incrementId' => 'KLEVU0000012345',
                    'storeId' => 1,
                    'result' => 'Fail',
                ],
            ],
        );
        $mockPipelineBuilder = $this->getMockPipelineBuilder(
            pipeline: $mockPipeline,
        );

        $processOrderEventsService = $this->initProcessEventsService([
            'pipelineBuilder' => $mockPipelineBuilder,
        ]);

        $result = $processOrderEventsService->execute(
            searchCriteria: null,
            via: 'PHPUnit',
        );

        // Regardless of individual records' results (in this case, Fail), the _pipeline_
        //  completes successfully so we have a success status
        $this->assertSame(
            expected: ProcessEventsResultStatuses::SUCCESS,
            actual: $result->getStatus(),
            message: 'Status',
        );
        $this->assertSame(
            expected: [
                [
                    'orderId' => 12345,
                    'incrementId' => 'KLEVU0000012345',
                    'storeId' => 1,
                    'result' => 'Fail',
                ],
            ],
            actual: $result->getPipelineResult(),
            message: 'Pipeline Result',
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $result->getMessages(),
            message: 'Messages',
        );
    }

    public function testExecute_PipelineCompletesNoop(): void
    {
        $mockPipeline = $this->getMockPipelineWithResult(
            pipelineResult: [],
        );
        $mockPipelineBuilder = $this->getMockPipelineBuilder(
            pipeline: $mockPipeline,
        );

        $processOrderEventsService = $this->initProcessEventsService([
            'pipelineBuilder' => $mockPipelineBuilder,
        ]);

        $result = $processOrderEventsService->execute(
            searchCriteria: null,
            via: 'PHPUnit',
        );

        $this->assertSame(
            expected: ProcessEventsResultStatuses::NOOP,
            actual: $result->getStatus(),
            message: 'Status',
        );
        $this->assertSame(
            expected: [],
            actual: $result->getPipelineResult(),
            message: 'Pipeline Result',
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $result->getMessages(),
            message: 'Messages',
        );
    }

    /**
     * @return mixed[]
     */
    public static function dataProvider_testExecute_PipelineThrowsExpectedException(): array
    {
        $return = [];

        $extractionException = new ExtractionException(
            message: 'Extraction exception',
        );
        $return[] = [
            $extractionException,
            [
                'Extraction exception',
            ],
        ];

        $sdkValidationException = new PhpSDKValidationException(
            errors: [
                'Error occurred in SDK Validation',
            ],
            message: 'SDK Validation exception',
        );
        $return[] = [
            $sdkValidationException,
            [
                'Error occurred in SDK Validation',
                'SDK Validation exception',
            ],
        ];

        $pipelinesValidationException = new PipelinesValidationException(
            validatorName: '',
            errors: [
                'Validator failed in pipeline',
            ],
            data: [
                'foo',
            ],
            message: 'Pipelines Validation Exception',
            previous: $sdkValidationException,
        );
        $return[] = [
            $pipelinesValidationException,
            [
                'Validator failed in pipeline',
                'Pipelines Validation Exception',
            ],
        ];

        $transformationException = new TransformationException(
            transformerName: '',
            errors: [
                'Error Message',
            ],
            message: 'Transformation exception',
        );
        $return[] = [
            $transformationException,
            [
                'Transformation exception',
                'Error Message',
            ],
        ];

        $invalidStatusTransitionException = new InvalidStatusTransitionException(
            phrase: __('Invalid status transition'),
        );
        $return[] = [
            $invalidStatusTransitionException,
            [
                'Invalid status transition',
            ],
        ];

        $localizedException = new LocalizedException(
            phrase: __('Localized Exception'),
            cause: $pipelinesValidationException,
        );
        $return[] = [
            $localizedException,
            [
                'Localized Exception',
            ],
        ];

        return $return;
    }

    /**
     * @dataProvider dataProvider_testExecute_PipelineThrowsExpectedException
     * @param \Exception $thrownException
     * @param string[] $expectedMessages
     * @return void
     * @throws NotFoundException
     */
    public function testExecute_PipelineThrowsExpectedException(
        \Exception $thrownException,
        array $expectedMessages,
    ): void {
        $mockPipeline = $this->getMockPipelineWithException(
            exception: $thrownException,
        );
        $mockPipelineBuilder = $this->getMockPipelineBuilder(
            pipeline: $mockPipeline,
        );

        $processOrderEventsService = $this->initProcessEventsService([
            'pipelineBuilder' => $mockPipelineBuilder,
        ]);

        $result = $processOrderEventsService->execute(
            searchCriteria: null,
            via: 'PHPUnit',
        );

        $this->assertSame(
            expected: ProcessEventsResultStatuses::ERROR,
            actual: $result->getStatus(),
            message: 'Status',
        );
        $this->assertSame(
            expected: null,
            actual: $result->getPipelineResult(),
            message: 'Pipeline Result',
        );
        $messages = $result->getMessages();
        $this->assertCount(
            expectedCount: count($expectedMessages),
            haystack: $messages,
            message: 'Messages',
        );
        foreach ($expectedMessages as $expectedMessage) {
            $this->assertContains(
                needle: $expectedMessage,
                haystack: $messages,
            );
        }
    }

    public function testExecute_PipelineThrowsUnexpectedException(): void
    {
        $thrownException = new \Exception(
            message: 'Generic Test Exception',
        );
        $mockPipeline = $this->getMockPipelineWithException(
            exception: $thrownException,
        );
        $mockPipelineBuilder = $this->getMockPipelineBuilder(
            pipeline: $mockPipeline,
        );

        $processOrderEventsService = $this->initProcessEventsService([
            'pipelineBuilder' => $mockPipelineBuilder,
        ]);

        $this->expectException($thrownException::class);
        $this->expectExceptionMessage($thrownException->getMessage());

        $processOrderEventsService->execute(
            searchCriteria: null,
            via: 'PHPUnit',
        );
    }

    /**
     * @param mixed[] $constructorArgs
     * @return ProcessEvents
     * @throws NotFoundException
     */
    private function initProcessEventsService(array $constructorArgs = []): ProcessEvents
    {
        return new ProcessEvents(
            parseFilepathAction: $constructorArgs['parseFilepathAction']
                ?? $this->getMockParseFilepathAction(),
            pipelineBuilder: $constructorArgs['pipelineBuilder']
                ?? $this->getMockPipelineBuilder(),
            eventsDataProvider: $constructorArgs['eventsDataProvider']
                ?? $this->getMockEventsDataProvider(),
            pipelineContextFactory: $constructorArgs['pipelineContextFactory']
                ?? $this->getMockPipelineContextFactory(),
            pipelineContextProviders: $constructorArgs['pipelineContextProviders']
                ?? [],
            processEventsResultFactory: $constructorArgs['processEventsResultFactory']
                ?? $this->getMockProcessEventsResultFactory(),
            pipelineConfigurationFilepath: $constructorArgs['pipelineConfigurationFilepath']
                ?? '',
            pipelineConfigurationOverrideFilepaths: $constructorArgs['pipelineConfigurationOverrideFilepaths']
                ?? [],
        );
    }

    /**
     * @return MockObject&ParseFilepathActionInterface
     */
    private function getMockParseFilepathAction(): MockObject&ParseFilepathActionInterface
    {
        return $this->getMockBuilder(ParseFilepathActionInterface::class)
            ->getMock();
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
     * @param mixed|null $pipelineResult
     * @return MockObject&PipelineInterface
     */
    private function getMockPipelineWithResult(
        mixed $pipelineResult = null,
    ): MockObject&PipelineInterface {
        $mockPipeline = $this->getMockPipeline();

        $mockPipeline->expects($this->once())
            ->method('execute')
            ->willReturn($pipelineResult);

        return $mockPipeline;
    }

    /**
     * @param \Exception $exception
     * @return MockObject&PipelineInterface
     */
    private function getMockPipelineWithException(
        \Exception $exception,
    ): MockObject&PipelineInterface {
        $mockPipeline = $this->getMockPipeline();

        $mockPipeline->expects($this->once())
            ->method('execute')
            ->willThrowException($exception);

        return $mockPipeline;
    }

    /**
     * @return MockObject&PipelineBuilderInterface
     */
    private function getMockPipelineBuilder(
        ?PipelineInterface $pipeline = null,
    ): MockObject&PipelineBuilderInterface {
        $pipeline ??= $this->getMockPipeline();

        $mockPipelineBuilder = $this->getMockBuilder(PipelineBuilderInterface::class)
            ->getMock();
        $mockPipelineBuilder->method('buildFromFiles')
            ->willReturn($pipeline);
        $mockPipelineBuilder->method('build')
            ->willReturn($pipeline);

        return $mockPipelineBuilder;
    }

    /**
     * @return MockObject&EventsDataProviderInterface
     */
    private function getMockEventsDataProvider(): MockObject&EventsDataProviderInterface
    {
        return $this->getMockBuilder(EventsDataProviderInterface::class)
            ->getMock();
    }

    /**
     * @param mixed[] $contextData
     * @return MockObject&PipelineContextFactory
     */
    private function getMockPipelineContextFactory(array $contextData = []): MockObject&PipelineContextFactory
    {
        $mockPipelineContext = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipelineContext->method('offsetGet')
            ->willReturnCallback(
                static fn (string|int $key): mixed => $contextData[$key] ?? null,
            );

        $mockPipelineContextFactory = $this->getMockBuilder(PipelineContextFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipelineContextFactory->method('create')
            ->willReturn($mockPipelineContext);

        return $mockPipelineContextFactory;
    }

    /**
     * @return MockObject&ProcessEventsResultInterfaceFactory
     */
    private function getMockProcessEventsResultFactory(): MockObject&ProcessEventsResultInterfaceFactory
    {
        $mockProcessEventsResultFactory = $this->getMockBuilder(ProcessEventsResultInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockProcessEventsResultFactory->method('create')
            ->willReturn(new ProcessEventsResult());

        return $mockProcessEventsResultFactory;
    }
}
