<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Service;

use Klevu\AnalyticsApi\Api\Data\ProcessEventsResultInterface;
use Klevu\AnalyticsApi\Api\Data\ProcessEventsResultInterfaceFactory;
use Klevu\AnalyticsApi\Api\EventsDataProviderInterface;
use Klevu\AnalyticsApi\Api\ProcessEventsServiceInterface;
use Klevu\AnalyticsApi\Model\Source\ProcessEventsResultStatuses;
use Klevu\PhpSDK\Exception\ValidationException as PhpSDKValidationException;
use Klevu\Pipelines\Exception\ExtractionException;
use Klevu\Pipelines\Exception\ExtractionExceptionInterface;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelineConfigurationException;
use Klevu\Pipelines\Exception\Pipeline\StageException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Exception\TransformationExceptionInterface;
use Klevu\Pipelines\Exception\ValidationException as PipelinesValidationException;
use Klevu\Pipelines\Exception\ValidationExceptionInterface;
use Klevu\Pipelines\Pipeline\ContextFactory as PipelineContextFactory;
use Klevu\Pipelines\Pipeline\PipelineBuilderInterface;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Klevu\PlatformPipelines\Api\PipelineConfigurationProviderInterface;
use Klevu\PlatformPipelines\Api\PipelineContextProviderInterface;
use Klevu\PlatformPipelines\Exception\CouldNotGenerateConfigurationOverridesException;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;

class ProcessEvents implements ProcessEventsServiceInterface
{
    /**
     * @var PipelineBuilderInterface
     */
    private readonly PipelineBuilderInterface $pipelineBuilder;
    /**
     * @var EventsDataProviderInterface
     */
    private readonly EventsDataProviderInterface $eventsDataProvider;
    /**
     * @var PipelineContextFactory
     */
    private readonly PipelineContextFactory $pipelineContextFactory;
    /**
     * @var PipelineContextProviderInterface[]
     */
    private array $pipelineContextProviders = [];
    /**
     * @var ProcessEventsResultInterfaceFactory
     */
    private readonly ProcessEventsResultInterfaceFactory $processEventsResultFactory;
    /**
     * @var string
     */
    private readonly string $pipelineConfigurationFilepath;
    /**
     * @var string[]
     */
    private readonly array $pipelineConfigurationOverridesFilepaths;
    /**
     * @var ConfigurationOverridesHandlerInterface
     */
    private ConfigurationOverridesHandlerInterface $configurationOverridesHandler;

    /**
     * @param PipelineBuilderInterface $pipelineBuilder
     * @param EventsDataProviderInterface $eventsDataProvider
     * @param PipelineContextFactory $pipelineContextFactory
     * @param PipelineContextProviderInterface[] $pipelineContextProviders
     * @param ProcessEventsResultInterfaceFactory $processEventsResultFactory
     * @param string $pipelineIdentifier
     * @param PipelineConfigurationProviderInterface $pipelineConfigurationProvider
     * @param ConfigurationOverridesHandlerInterface $configurationOverridesHandler
     *
     * @throws NotFoundException
     */
    public function __construct(
        PipelineBuilderInterface $pipelineBuilder,
        EventsDataProviderInterface $eventsDataProvider,
        PipelineContextFactory $pipelineContextFactory,
        array $pipelineContextProviders,
        ProcessEventsResultInterfaceFactory $processEventsResultFactory,
        string $pipelineIdentifier,
        PipelineConfigurationProviderInterface $pipelineConfigurationProvider,
        ConfigurationOverridesHandlerInterface $configurationOverridesHandler,
    ) {
        $this->pipelineBuilder = $pipelineBuilder;
        $this->eventsDataProvider = $eventsDataProvider;
        $this->pipelineContextFactory = $pipelineContextFactory;
        array_walk(
            $pipelineContextProviders,
            [$this, 'addPipelineContextProvider'],
        );
        $this->processEventsResultFactory = $processEventsResultFactory;
        $this->pipelineConfigurationFilepath = $pipelineConfigurationProvider
            ->getPipelineConfigurationFilepathByIdentifier($pipelineIdentifier);
        $this->pipelineConfigurationOverridesFilepaths = $pipelineConfigurationProvider
            ->getPipelineConfigurationOverridesFilepathsByIdentifier($pipelineIdentifier);
        $this->configurationOverridesHandler = $configurationOverridesHandler;
    }

    /**
     * @param SearchCriteriaInterface|null $searchCriteria
     * @param string $via
     *
     * @return ProcessEventsResultInterface
     * @throws CouldNotGenerateConfigurationOverridesException
     * @throws ExtractionExceptionInterface
     * @throws InvalidPipelineConfigurationException
     * @throws TransformationExceptionInterface
     * @throws ValidationExceptionInterface
     */
    public function execute(
        ?SearchCriteriaInterface $searchCriteria = null,
        string $via = '',
    ): ProcessEventsResultInterface {
        $pipeline = $this->buildPipeline();
        $messages = [];
        try {
            $pipelineResult = $pipeline->execute(
                payload: $this->eventsDataProvider->get($searchCriteria),
                context: $this->getPipelineContext($via),
            );
            $status = $pipelineResult
                ? ProcessEventsResultStatuses::SUCCESS
                : ProcessEventsResultStatuses::NOOP;
        } catch (ExtractionException | PhpSDKValidationException | PipelinesValidationException | TransformationException $pipelineException) { // phpcs:ignore Generic.Files.LineLength.TooLong
            $status = ProcessEventsResultStatuses::ERROR;
            $messages = array_merge(
                $messages,
                [$pipelineException->getMessage()],
                method_exists($pipelineException, 'getErrors') ? $pipelineException->getErrors() : [],
            );
        } catch (StageException $exception) {
            $status = ProcessEventsResultStatuses::ERROR;
            $pipeline = $exception->getPipeline();
            $previousException = $exception->getPrevious();

            $messages = array_merge(
                $messages,
                [
                    __(
                        'Encountered error in pipeline stage "%1": %2',
                        $pipeline->getIdentifier(),
                        $previousException?->getMessage() ?: '',
                    )->render(),
                ],
                method_exists($exception, 'getErrors') ? $exception->getErrors() : [],
                $previousException && method_exists($previousException, 'getErrors')
                    ? $previousException->getErrors()
                    : [],
            );
        } catch (LocalizedException $exception) {
            $status = ProcessEventsResultStatuses::ERROR;
            $messages[] = $exception->getMessage();
        }

        $return = $this->processEventsResultFactory->create();
        $return->setStatus($status);
        $return->setPipelineResult($pipelineResult ?? null);
        $return->setMessages($messages);

        return $return;
    }

    /**
     * @param PipelineContextProviderInterface $pipelineContextProvider
     * @param string $contextKey
     * @return void
     */
    private function addPipelineContextProvider(
        PipelineContextProviderInterface $pipelineContextProvider,
        string $contextKey,
    ): void {
        $this->pipelineContextProviders[$contextKey] = $pipelineContextProvider;
    }

    /**
     * @param string $via
     * @return \ArrayAccess<int|string, mixed>
     */
    private function getPipelineContext(
        string $via,
    ): \ArrayAccess {
        $data = array_map(
            static fn (
                PipelineContextProviderInterface $pipelineContextProvider,
            ): array|object => $pipelineContextProvider->get(),
            $this->pipelineContextProviders,
        );

        $data['system'] ??= [];
        $data['system']['via'] = $via;

        return $this->pipelineContextFactory->create([
            'data' => $data,
        ]);
    }

    /**
     * @return PipelineInterface
     * @throws InvalidPipelineConfigurationException
     * @throws CouldNotGenerateConfigurationOverridesException
     */
    private function buildPipeline(): PipelineInterface
    {
        try {
            $this->configurationOverridesHandler->execute();
            $pipeline = $this->pipelineBuilder->buildFromFiles(
                configurationFilepath: $this->pipelineConfigurationFilepath,
                overridesFilepaths: $this->pipelineConfigurationOverridesFilepaths,
            );
        } catch (\TypeError $exception) { // @phpstan-ignore-line TypeError can be thrown by buildFromFiles
            throw new InvalidPipelineConfigurationException(
                pipelineName: null,
                message: $exception->getMessage(),
                code: $exception->getCode(),
                previous: $exception,
            );
        }

        return $pipeline;
    }
}
