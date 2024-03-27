<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\ViewModel;

use Magento\Framework\Escaper as CoreEscaper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Escaper implements ArgumentInterface
{
    /**
     * @var CoreEscaper
     */
    private readonly CoreEscaper $escaper;

    /**
     * @param CoreEscaper $escaper
     */
    public function __construct(
        CoreEscaper $escaper,
    ) {
        $this->escaper = $escaper;
    }

    /**
     * @return CoreEscaper
     */
    public function getEscaper(): CoreEscaper
    {
        return $this->escaper;
    }
}
