<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Traits;

use Klevu\AnalyticsApi\Model\AndFilter;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

trait FilterCollectionTrait
{
    /**
     * @param AbstractCollection $collection
     * @param SearchCriteriaInterface $searchCriteria
     * @return AbstractCollection
     */
    private function filterCollection(
        AbstractCollection $collection,
        SearchCriteriaInterface $searchCriteria,
    ): AbstractCollection {
        // Add fields to filter
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $collection = $this->applyFilterGroupToCollection(
                collection: $collection,
                filterGroup: $filterGroup,
            );
        }

        // Set sort orders
        foreach ($searchCriteria->getSortOrders() ?? [] as $sortOrder) {
            if (!($sortOrder instanceof SortOrder)) {
                continue;
            }

            $collection->addOrder(
                field: $sortOrder->getField(),
                direction: match ($sortOrder->getDirection()) {
                    SortOrder::SORT_ASC => SortOrder::SORT_ASC,
                    default => SortOrder::SORT_DESC,
                },
            );
        }

        // Pagination
        $currentPage = $searchCriteria->getCurrentPage();
        $pageSize = $searchCriteria->getPageSize();
        if ($pageSize) {
            $select = $collection->getSelect();
            $select->limit(
                count: $pageSize,
                offset: ($currentPage - 1) * $pageSize,
            );
        }

        return $collection;
    }

    /**
     * @param AbstractCollection $collection
     * @param FilterGroup $filterGroup
     * @return AbstractCollection
     */
    private function applyFilterGroupToCollection(
        AbstractCollection $collection,
        FilterGroup $filterGroup,
    ): AbstractCollection {
        $fields = [];
        $conditions = [];

        foreach ($filterGroup->getFilters() as $filter) {
            switch (true) {
                case $filter instanceof AndFilter && $filter->getFilters():
                    $childFields = [];
                    $childConditions = [];
                    foreach ($filter->getFilters() as $childFilter) {
                        $childFields[] = $childFilter->getField();
                        $childConditions[] = [
                            $childFilter->getConditionType() ?: 'eq' => $childFilter->getValue(),
                        ];
                    }
                    $fields[] = $childFields;
                    $conditions[] = $childConditions;
                    break;

                case $filter instanceof Filter:
                    $fields[] = $filter->getField();
                    $conditions[] = [
                        $filter->getConditionType() ?: 'eq' => $filter->getValue(),
                    ];
                    break;
            }
        }

        if ($fields) {
            $collection->addFieldToFilter(
                field: $fields,
                condition: $conditions,
            );
        }

        return $collection;
    }

    /**
     * @param AbstractCollection $collection
     * @param SearchCriteriaInterface $searchCriteria
     * @return array<mixed[]|int>
     */
    private function getSearchResultData(
        AbstractCollection $collection,
        SearchCriteriaInterface $searchCriteria,
    ): array {
        $lastPageNumber = $collection->getLastPageNumber();
        $currentPage = $searchCriteria->getCurrentPage();
        $pageSize = $searchCriteria->getPageSize();
        /*
         * If a collection page is requested that does not exist, Magento reverts to get the first page
         * of that collection using this plugin \Magento\Theme\Plugin\Data\Collection::afterGetCurPage.
         * We do not want that behaviour here, return empty result instead.
         * Only do this where currentPage and pageSize are set in searchCriteria
         */
        $invalidPage = $currentPage && $pageSize && $lastPageNumber < $currentPage;

        return [
            $invalidPage ? [] : $collection->getItems(),
            $collection->getSize(),
        ];
    }
}
