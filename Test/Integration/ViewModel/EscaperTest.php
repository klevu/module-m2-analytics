<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Analytics\Test\Integration\ViewModel;

use Klevu\Analytics\ViewModel\Escaper as EscaperViewModel;
use Magento\Framework\Escaper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class EscaperTest extends TestCase
{
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
    }

    public function testInterfaceGeneration(): void
    {
        $this->assertInstanceOf(
            ArgumentInterface::class,
            $this->objectManager->get(EscaperViewModel::class),
        );
    }

    public function testGetEscaper(): void
    {
        /** @var EscaperViewModel $viewModel */
        $viewModel = $this->objectManager->get(EscaperViewModel::class);

        $this->assertInstanceof(
            Escaper::class,
            $viewModel->getEscaper(),
        );
    }
}
