<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Console\Command;

use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigurationOverridesRegenerateCommand extends Command
{
    public const COMMAND_NAME = 'klevu:analytics:configuration-overrides-regenerate';
    public const OPTION_ENTITY_TYPE = 'entity-type';

    /**
     * @var array<string, ConfigurationOverridesHandlerInterface[]>
     */
    private array $configurationOverridesHandler = [];

    /**
     * @param array<string, ConfigurationOverridesHandlerInterface[]> $configurationOverridesHandlers
     * @param string|null $name
     */
    public function __construct(
        array $configurationOverridesHandlers,
        ?string $name = null,
    ) {
        foreach ($configurationOverridesHandlers as $entityType => $configurationOverridesHandlersForEntityType) {
            array_walk(
                $configurationOverridesHandlersForEntityType,
                function (ConfigurationOverridesHandlerInterface $configurationOverridesHandler) use ($entityType): void { // phpcs:ignore Generic.Files.LineLength.TooLong
                    $this->addConfigurationOverridesHandler(
                        configurationOverridesHandler: $configurationOverridesHandler,
                        entityType: $entityType,
                    );
                },
            );
        }

        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName(static::COMMAND_NAME);
        $this->setDescription(
            __(
                'Regenerates analytics pipeline overrides. '
                    . 'Warning: this will overwrite any modifications made to existing versions of these files',
            )->render(),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_TYPE,
            mode: InputOption::VALUE_OPTIONAL + InputOption::VALUE_IS_ARRAY,
            description: __(
                'Regenerate overrides for this entity type only',
            )->render(),
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): int {
        $return = Cli::RETURN_SUCCESS;
        $entityTypes = $input->getOption(static::OPTION_ENTITY_TYPE);

        foreach ($this->configurationOverridesHandler as $entityType => $configurationOverridesHandlersForEntityType) {
            if ($entityTypes && !in_array($entityType, $entityTypes, true)) {
                continue;
            }

            foreach ($configurationOverridesHandlersForEntityType as $configurationOverridesHandler) {
                $configurationOverridesHandler->execute();
            }
        }

        return $return;
    }

    /**
     * @param ConfigurationOverridesHandlerInterface $configurationOverridesHandler
     * @param string $entityType
     *
     * @return void
     */
    private function addConfigurationOverridesHandler(
        ConfigurationOverridesHandlerInterface $configurationOverridesHandler,
        string $entityType,
    ): void {
        $this->configurationOverridesHandler[$entityType] ??= [];
        $this->configurationOverridesHandler[$entityType][] = $configurationOverridesHandler;
    }
}
