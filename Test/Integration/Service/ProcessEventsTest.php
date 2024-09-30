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
use Klevu\PlatformPipelines\Api\PipelineConfigurationOverridesFilepathsProviderInterface;
use Klevu\PlatformPipelines\Api\PipelineConfigurationProviderInterface;
use Klevu\PlatformPipelines\Api\PipelineContextProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @method ProcessEvents instantiateTestObject(array $args = [])
 * @covers \Klevu\Analytics\Service\ProcessEvents
 */
class ProcessEventsTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = ProcessEvents::class;
        $this->interfaceFqcn = ProcessEventsServiceInterface::class;

        /** @var MockObject&PipelineConfigurationProviderInterface $mockPipelineConfigurationProvider */
        $mockPipelineConfigurationProvider = $this->getMockBuilder(PipelineConfigurationProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipelineConfigurationProvider->method('getPipelineConfigurationFilepathByIdentifier')
            ->willReturn('/foo/bar.yml');
        $this->constructorArgumentDefaults = [
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationProvider' => $mockPipelineConfigurationProvider,
            'pipelineIdentifier' => 'PHPUNIT::test',
        ];
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
        $pipelineIdentifier = 'ORDER::queued';

        $this->expectException(RuntimeException::class);

        $this->instantiateTestObject([
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineContextProviders' => $pipelineContextProviders,
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: __DIR__ . '/fixtures/pipeline/process-events.yml',
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
        ]);
    }

    public function testConstruct_PipelineConfigurationFilepathNotFound(): void
    {
        $pipelineIdentifier = 'ORDER::queued';

        $this->expectException(NotFoundException::class);

        $this->instantiateTestObject([
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: '/foo/bar',
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
        ]);
    }

    public function testExecute_HandleInvalidPipelineConfiguration(): void
    {
        $pipelineIdentifier = 'ORDER::queued';

        $processEventsService = $this->instantiateTestObject([
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: __DIR__ . '/fixtures/pipeline/process-events.invalid.yml',
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
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
        $pipelineIdentifier = 'ORDER::queued';

        $mockPipeline = $this->getMockPipeline();
        $mockPipeline->method('execute')
            ->willThrowException($exception);

        $mockPipelineBuilder = $this->getMockPipelineBuilder(
            pipeline: $mockPipeline,
        );

        $processEventsService = $this->instantiateTestObject([
            'pipelineBuilder' => $mockPipelineBuilder,
            'eventsDataProvider' => $this->getMockEventsDataProvider(),
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: __DIR__ . '/fixtures/pipeline/process-events.yml',
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
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
        $pipelineIdentifier = 'ORDER::queued';

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

        $processEventsService = $this->instantiateTestObject([
            'pipelineBuilder' => $mockPipelineBuilder,
            'eventsDataProvider' => $this->getMockEventsDataProvider([
                'foo',
                'bar',
                'baz',
            ]),
            'pipelineContextProviders' => [
                'test' => $this->getMockPipelineContextProvider(['wom' => 'bat']),
            ],
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: __DIR__ . '/fixtures/pipeline/process-events.yml',
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
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

    /**
     * @param string $pipelineIdentifier
     * @param string $pipelineConfigurationFilepath
     * @param string[] $pipelineConfigurationOverridesFilepaths
     *
     * @return PipelineConfigurationProviderInterface
     * @throws PHPUnitException
     */
    private function getPipelineConfigurationProvider(
        string $pipelineIdentifier,
        string $pipelineConfigurationFilepath,
        array $pipelineConfigurationOverridesFilepaths = [],
    ): PipelineConfigurationProviderInterface {
        /** @var MockObject&PipelineConfigurationOverridesFilepathsProviderInterface $mockPipelineConfigurationOverridesFilepathsProvider */
        $mockPipelineConfigurationOverridesFilepathsProvider = $this->getMockBuilder(
            className: PipelineConfigurationOverridesFilepathsProviderInterface::class,
        )->disableOriginalConstructor()
        ->getMock();
        $mockPipelineConfigurationOverridesFilepathsProvider
            ->method('get')
            ->willReturn($pipelineConfigurationOverridesFilepaths);

        return $this->objectManager->create(
            type: PipelineConfigurationProviderInterface::class,
            arguments: [
                'pipelineConfigurationFilepaths' => [
                    $pipelineIdentifier => $pipelineConfigurationFilepath,
                ],
                'pipelineConfigurationOverridesFilepathsProviders' => [
                    $pipelineIdentifier => $mockPipelineConfigurationOverridesFilepathsProvider,
                ],
            ],
        );
    }
}
