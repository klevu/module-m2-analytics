<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Service\Action;

use Magento\Framework\Exception\NotFoundException;

interface ParseFilepathActionInterface
{
    /**
     * @param string $filepath
     * @return string
     * @throws NotFoundException
     */
    public function execute(string $filepath): string;
}
