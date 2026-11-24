<?php

namespace App\Services\Reports;

/**
 * Immutable description of one downloadable report.
 */
final class ReportDefinition
{
    /**
     * @param  array<string, array{label: string, type: string}>  $columns
     * @param  list<string>  $statusOptions
     * @param  list<string>  $categoryOptions
     * @param  list<string>  $channelOptions
     * @param  list<string>  $typeOptions
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $category,
        public readonly string $description,
        public readonly string $dateBasis,
        public readonly array $columns,
        public readonly array $statusOptions = [],
        public readonly array $categoryOptions = [],
        public readonly array $channelOptions = [],
        public readonly array $typeOptions = [],
    ) {}

    /**
     * @return list<string>
     */
    public function columnKeys(): array
    {
        return array_keys($this->columns);
    }

    public function hasSecondaryFilters(): bool
    {
        return $this->statusOptions !== []
            || $this->categoryOptions !== []
            || $this->channelOptions !== []
            || $this->typeOptions !== [];
    }
}
