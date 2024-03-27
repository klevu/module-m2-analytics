<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Model\ResourceModel\Collection;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Select;

trait ExtendedAddFieldToFilterTrait
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    // phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
    /**
     * Extension of core addFieldToFilter to support nested
     * (cond) AND ((cond) OR ((cond) AND (cond))) queries
     *
     * @param string|string[]|string[][] $field
     * @param string|mixed[] $condition
     * @return AbstractDb|self
     * @see AbstractDb::addFieldToFilter()
     */
    public function addFieldToFilter(
        $field,
        $condition = null,
    ) {
        if (!($this instanceof AbstractDb) || !method_exists(parent::class, 'addFieldToFilter')) {
            throw new \LogicException(sprintf(
                '%s trait can only be applied to objects extending type %s',
                self::class,
                AbstractDb::class,
            ));
        }

        $isExtendedArguments = is_array($field)
            && is_array($condition)
            && array_filter(
                $field,
                static fn ($fieldItem): bool => is_array($fieldItem),
            );

        if (!$isExtendedArguments) {
            return parent::addFieldToFilter(
                field: $field,
                condition: $condition,
            );
        }

        $conditions = [];
        foreach ($field as $fieldKey => $fieldValue) {
            if (!is_array($fieldValue)) {
                $conditions[] = $this->_translateCondition(
                    field: $fieldValue,
                    condition: $condition[$fieldKey] ?? null,
                );

                continue;
            }

            $andConditions = [];
            foreach ($fieldValue as $andFieldKey => $andFieldValue) {
                $andConditions[] = $this->_translateCondition(
                    field: $andFieldValue,
                    condition: $condition[$fieldKey][$andFieldKey] ?? null,
                );
            }
            $conditions[] = '('
                . implode(') ' . Select::SQL_AND . ' (', $andConditions)
                . ')';
        }
        $resultCondition = '('
            . implode(') ' . Select::SQL_OR . ' (', $conditions)
            . ')';

        $select = $this->getSelect();
        $select->where(
            cond: $resultCondition, // phpcs:ignore Security.Drupal7.DynQueries.D7DynQueriesDirectVar
            type: Select::TYPE_CONDITION,
        );

        return $this;
    }
    // phpcs:enable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    // phpcs:enable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint

    /**
     * @param string[]|string $field
     * @return string[]
     */
    private function extractFieldsForFilter(array|string $field): array
    {
        if (is_string($field)) {
            $mapper = $this->_getMapper() ?: [];

            return [$mapper['fields'][$field] ?? $field];
        }

        $fields = array_map(
            fn (array|string $fieldValue): array => $this->extractFieldsForFilter($fieldValue),
            $field,
        );

        return array_unique(
            array_merge([], ...$fields),
        );
    }

    /**
     * @param string[]|string $field
     * @return string[]
     */
    private function extractTablesForFilter(array|string $field): array
    {
        if (!method_exists($this, 'getMainTable')) {
            throw new \LogicException(sprintf(
                '%s trait can only be applied to objects implementing method "getMainTable"',
                self::class,
            ));
        }

        $mainTable = $this->getMainTable();
        $tables = array_map(
            static function (string $fullFieldName) use ($mainTable): string {
                [$tableAlias, $fieldName] = array_pad(
                    array: explode('.', $fullFieldName),
                    length: 2,
                    value: null,
                );

                return ($fieldName)
                    ? $tableAlias
                    : $mainTable;
            },
            $this->extractFieldsForFilter($field),
        );

        return array_unique($tables);
    }

    /**
     * @param string $tableAlias
     * @return bool
     * @throws \Zend_Db_Select_Exception
     */
    private function isTableJoined(string $tableAlias): bool
    {
        $select = $this->getSelect();
        $from = $select->getPart(Select::FROM);

        return is_array($from)
            && array_key_exists($tableAlias, $from);
    }
}
