<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Model\Source\Options;

use Klevu\AnalyticsApi\Model\Source\ProcessEventsResultStatuses;
use Magento\Framework\Data\OptionSourceInterface;

class ProcessEventsResultStatus implements OptionSourceInterface
{
    /**
     * @var mixed[][]|null
     */
    private ?array $options = null;

    /**
     * @return mixed[][]
     */
    public function toOptionArray(): array
    {
        if (null === $this->options) {
            $this->options = [
                [
                    'value' => ProcessEventsResultStatuses::SUCCESS->value,
                    'label' => __('Success'),
                ],
                [
                    'value' => ProcessEventsResultStatuses::ERROR->value,
                    'label' => __('Error'),
                ],
                [
                    'value' => ProcessEventsResultStatuses::NOOP->value,
                    'label' => __('No Action'),
                ],
            ];
        }

        return $this->options;
    }
}
