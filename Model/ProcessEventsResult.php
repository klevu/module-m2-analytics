<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Model;

use Klevu\AnalyticsApi\Api\Data\ProcessEventsResultInterface;
use Klevu\AnalyticsApi\Model\Source\ProcessEventsResultStatuses;

class ProcessEventsResult implements ProcessEventsResultInterface
{
    /**
     * @var ProcessEventsResultStatuses|null
     */
    private ?ProcessEventsResultStatuses $status = null;
    /**
     * @var string[]
     */
    private array $messages = [];
    /**
     * @var mixed
     */
    private mixed $pipelineResult = null;

    /**
     * @return ProcessEventsResultStatuses|null
     */
    public function getStatus(): ?ProcessEventsResultStatuses
    {
        return $this->status;
    }

    /**
     * @param ProcessEventsResultStatuses $status
     * @return void
     */
    public function setStatus(ProcessEventsResultStatuses $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param string[] $messages
     * @return void
     */
    public function setMessages(array $messages): void
    {
        $this->messages = array_map('strval', $messages);
    }

    /**
     * @return mixed
     */
    public function getPipelineResult(): mixed
    {
        return $this->pipelineResult;
    }

    /**
     * @param mixed $pipelineResult
     * @return void
     */
    public function setPipelineResult(mixed $pipelineResult): void
    {
        $this->pipelineResult = $pipelineResult;
    }
}
