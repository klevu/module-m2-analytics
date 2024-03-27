<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Service;

use Klevu\Analytics\Service\Action\ParseFilepathActionInterface;
use Klevu\AnalyticsApi\Api\Data\ProcessEventsResultInterface;
use Klevu\AnalyticsApi\Api\Data\ProcessEventsResultInterfaceFactory;
use Klevu\AnalyticsApi\Api\EventsDataProviderInterface;
use Klevu\AnalyticsApi\Api\ProcessEventsServiceInterface;
use Klevu\AnalyticsApi\Model\Source\ProcessEventsResultStatuses;
use Klevu\PhpSDK\Exception\ValidationException as PhpSDKValidationException;
use Klevu\Pipelines\Exception\ExtractionException;
use Klevu\Pipelines\Exception\ExtractionExceptionInterface;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Exception\TransformationExceptionInterface;
use Klevu\Pipelines\Exception\ValidationException as PipelinesValidationException;
use Klevu\Pipelines\Exception\ValidationExceptionInterface;
use Klevu\Pipelines\Pipeline\ContextFactory as PipelineContextFactory;
use Klevu\Pipelines\Pipeline\PipelineBuilderInterface;
use Klevu\PlatformPipelines\Api\PipelineContextProviderInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;

class ProcessEvents implements ProcessEventsServiceInterface
{
    /**
     * @var ParseFilepathActionInterface
     */
    private readonly ParseFilepathActionInterface $parseFilepathAction;
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
    private array $pipelineConfigurationOverrideFilepaths = [];

    /**
     * @param ParseFilepathActionInterface $parseFilepathAction
     * @param PipelineBuilderInterface $pipelineBuilder
     * @param EventsDataProviderInterface $eventsDataProvider
     * @param PipelineContextFactory $pipelineContextFactory
     * @param PipelineContextProviderInterface[] $pipelineContextProviders
     * @param ProcessEventsResultInterfaceFactory $processEventsResultFactory
     * @param string $pipelineConfigurationFilepath
     * @param string[] $pipelineConfigurationOverrideFilepaths
     * @throws NotFoundException
     */
    public function __construct(
        ParseFilepathActionInterface $parseFilepathAction,
        PipelineBuilderInterface $pipelineBuilder,
        EventsDataProviderInterface $eventsDataProvider,
        PipelineContextFactory $pipelineContextFactory,
        array $pipelineContextProviders,
        ProcessEventsResultInterfaceFactory $processEventsResultFactory,
        string $pipelineConfigurationFilepath,
        array $pipelineConfigurationOverrideFilepaths = [],
    ) {
        $this->parseFilepathAction = $parseFilepathAction;
        $this->pipelineBuilder = $pipelineBuilder;
        $this->eventsDataProvider = $eventsDataProvider;
        $this->pipelineContextFactory = $pipelineContextFactory;
        array_walk(
            $pipelineContextProviders,
            [$this, 'addPipelineContextProvider'],
        );
        $this->processEventsResultFactory = $processEventsResultFactory;
        $this->pipelineConfigurationFilepath = $this->parseFilepathAction->execute($pipelineConfigurationFilepath);
        array_walk(
            $pipelineConfigurationOverrideFilepaths,
            [$this, 'addPipelineConfigurationOverrideFilepath'],
        );
    }

    /**
     * @param SearchCriteriaInterface|null $searchCriteria
     * @param string $via
     *
     * @return ProcessEventsResultInterface
     * @throws ExtractionExceptionInterface
     * @throws TransformationExceptionInterface
     * @throws ValidationExceptionInterface
     */
    public function execute(
        ?SearchCriteriaInterface $searchCriteria = null,
        string $via = '',
    ): ProcessEventsResultInterface {
        $pipeline = $this->pipelineBuilder->buildFromFiles(
            configurationFilepath: $this->pipelineConfigurationFilepath,
            overridesFilepaths: $this->pipelineConfigurationOverrideFilepaths,
        );

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
        } catch (LocalizedException $exception) {
            $status = ProcessEventsResultStatuses::ERROR;
            $messages = array_merge(
                $messages,
                [$exception->getMessage()],
            );
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
     * @param string $pipelineConfigurationOverrideFilepath
     * @return void
     * @throws NotFoundException
     */
    private function addPipelineConfigurationOverrideFilepath(string $pipelineConfigurationOverrideFilepath): void
    {
        $parsedFilepath = $this->parseFilepathAction->execute($pipelineConfigurationOverrideFilepath);
        if (!in_array($parsedFilepath, $this->pipelineConfigurationOverrideFilepaths, true)) {
            $this->pipelineConfigurationOverrideFilepaths[] = $parsedFilepath;
        }
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
}
