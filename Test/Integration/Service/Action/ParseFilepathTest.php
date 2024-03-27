<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Analytics\Test\Integration\Service\Action;

use Klevu\Analytics\Service\Action\ParseFilepath;
use Klevu\Analytics\Service\Action\ParseFilepathActionInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class ParseFilepathTest extends TestCase
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
            ParseFilepath::class,
            $this->objectManager->get(ParseFilepathActionInterface::class),
        );
    }

    public function testExecute_ModuleFilepath_NotExists(): void
    {
        /** @var ParseFilepath $parseFilepathAction */
        $parseFilepathAction = $this->objectManager->get(ParseFilepath::class);

        $filepath = 'Klevu_Analytics::etc/foo.xml';

        $this->expectException(NotFoundException::class);
        $parseFilepathAction->execute($filepath);
    }

    public function testExecute_ModuleFilepath_Exists(): void
    {
        /** @var ParseFilepath $parseFilepathAction */
        $parseFilepathAction = $this->objectManager->get(ParseFilepath::class);

        $filepath = 'Klevu_Analytics::etc/module.xml';
        $expectedResult = realpath(__DIR__ . '/../../../../etc/module.xml');

        $this->assertSame(
            $expectedResult,
            $parseFilepathAction->execute($filepath),
        );
    }

    public function testExecute_RelativeFilepath_NotExists(): void
    {
        /** @var ParseFilepath $parseFilepathAction */
        $parseFilepathAction = $this->objectManager->get(ParseFilepath::class);

        $filepath = 'app/etc/foo.xml';

        $this->expectException(NotFoundException::class);
        $parseFilepathAction->execute($filepath);
    }

    public function testExecute_RelativeFilepath_Exists(): void
    {
        /** @var ParseFilepath $parseFilepathAction */
        $parseFilepathAction = $this->objectManager->get(ParseFilepath::class);

        $filepath = 'app/etc/env.php';

        /** @var DirectoryList $directoryList */
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $expectedResult = $directoryList->getRoot() . '/app/etc/env.php';

        $this->assertSame(
            $expectedResult,
            $parseFilepathAction->execute($filepath),
        );
    }

    public function testExecute_AbsoluteFilepath_NotExists(): void
    {
        /** @var ParseFilepath $parseFilepathAction */
        $parseFilepathAction = $this->objectManager->get(ParseFilepath::class);

        $filepath = '/foo/bar';

        $this->expectException(NotFoundException::class);
        $parseFilepathAction->execute($filepath);
    }

    public function testExecute_AbsoluteFilepath_Exists(): void
    {
        /** @var ParseFilepath $parseFilepathAction */
        $parseFilepathAction = $this->objectManager->get(ParseFilepath::class);

        $filepath = __DIR__ . '/../../../../etc/module.xml';
        $expectedResult = realpath($filepath);

        $this->assertSame(
            $expectedResult,
            $parseFilepathAction->execute($expectedResult),
        );
    }
}
