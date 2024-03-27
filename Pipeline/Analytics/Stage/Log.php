<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Pipeline\Analytics\Stage;

use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\Pipelines\Pipeline\Stage\Log as BaseLog;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

// Cannot use virtualType as no "physical" class exists for pipelineFqcnProvider to find and inject logger
class Log extends BaseLog
{
    /**
     * @param LoggerInterface $logger
     * @param PipelineInterface[] $stages
     * @param mixed[]|null $args
     * @param string $identifier
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        LoggerInterface $logger,
        array $stages = [],
        ?array $args = null,
        string $identifier = '',
    ) {
        parent::__construct(
            logger: $logger,
            stages: $stages,
            args: $args,
            identifier: $identifier,
        );
    }
}
