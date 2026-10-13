<?php

namespace App\Datasets;

/**
 * The datasets companies may import into. Adding a dataset means writing a
 * Dataset class (plus its table/model) and listing it here.
 */
class DatasetRegistry
{
    /**
     * @var array<string, Dataset>
     */
    private array $datasets = [];

    /**
     * @param  array<int, Dataset>  $datasets
     */
    public function __construct(array $datasets)
    {
        foreach ($datasets as $dataset) {
            $this->datasets[$dataset->key()] = $dataset;
        }
    }

    /**
     * @return array<string, Dataset>
     */
    public function all(): array
    {
        return $this->datasets;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->datasets);
    }

    public function get(string $key): ?Dataset
    {
        return $this->datasets[$key] ?? null;
    }

    public function getOrFail(string $key): Dataset
    {
        return $this->get($key) ?? abort(404, 'Unknown dataset.');
    }
}
