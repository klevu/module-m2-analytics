<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Pipeline\Analytics\Stage;

use Klevu\Configuration\Service\Provider\StoreScopeProviderInterface;
use Klevu\Pipelines\Exception\Pipeline\StageException;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\Pipelines\Pipeline\StagesNotSupportedTrait;
use Magento\Framework\Exception\LocalizedException;

class SetStoreScope implements PipelineInterface
{
    use StagesNotSupportedTrait;

    /**
     * @var StoreScopeProviderInterface
     */
    private readonly StoreScopeProviderInterface $storeScopeProvider;
    /**
     * @var string
     */
    private readonly string $identifier;

    /**
     * @param StoreScopeProviderInterface $storeScopeProvider
     * @param PipelineInterface[] $stages
     * @param mixed[]|null $args
     * @param string $identifier
     */
    public function __construct(
        StoreScopeProviderInterface $storeScopeProvider,
        array $stages = [],
        ?array $args = null,
        string $identifier = '',
    ) {
        $this->storeScopeProvider = $storeScopeProvider;

        array_walk($stages, [$this, 'addStage']);
        if ($args) {
            $this->setArgs($args);
        }

        $this->identifier = $identifier;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    // phpcs:disable Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
    /**
     * @param mixed[] $args
     * @return void
     */
    public function setArgs(
        array $args, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): void {
        // No args supported for this pipeline
    }
    // phpcs:enable Magento2.CodeAnalysis.EmptyBlock.DetectedFunction

    /**
     * @param mixed $payload
     * @param \ArrayAccess<int|string, mixed>|null $context
     * @return mixed
     * @throws StageException
     */
    public function execute(
        mixed $payload,
        ?\ArrayAccess $context = null, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): mixed {
        try {
            $this->storeScopeProvider->setCurrentStoreById($payload);
        } catch (LocalizedException $exception) {
            throw new StageException(
                pipeline: $this,
                previous: $exception,
            );
        }

        return $payload;
    }
}
