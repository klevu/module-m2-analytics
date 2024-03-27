<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Pipeline\Analytics\Stage;

use Klevu\PhpSDK\Service\Analytics\CollectService as AnalyticsCollectService;
use Klevu\PhpSDKPipelines\Pipeline\Stage\SendAnalyticsCollectEvents as BaseSendAnalyticsCollectEvents;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

// Cannot use virtualType as no "physical" class exists for pipelineFqcnProvider to find and inject collect service
class SendAnalyticsCollectEvents extends BaseSendAnalyticsCollectEvents
{
    /**
     * @param AnalyticsCollectService|null $analyticsCollectService
     * @param PipelineInterface[] $stages
     * @param mixed[]|null $args
     * @param string $identifier
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        ?AnalyticsCollectService $analyticsCollectService = null,
        array $stages = [],
        ?array $args = null,
        string $identifier = '',
    ) {
        parent::__construct(
            analyticsCollectService: $analyticsCollectService,
            stages: $stages,
            args: $args,
            identifier: $identifier,
        );
    }
}
