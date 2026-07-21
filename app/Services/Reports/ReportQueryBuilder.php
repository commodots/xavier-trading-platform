<?php

namespace App\Services\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportQueryBuilder
{
    protected Builder $query;
    protected array $allowedSorts = [];
    protected string $defaultSort = 'created_at';
    protected string $defaultSortDir = 'desc';

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function setAllowedSorts(array $sorts): self
    {
        $this->allowedSorts = $sorts;
        return $this;
    }

    public function setDefaultSort(string $column, string $dir = 'desc'): self
    {
        $this->defaultSort = $column;
        $this->defaultSortDir = $dir;
        return $this;
    }

    public function applyDateRange(?string $from, ?string $to, string $column = 'created_at'): self
    {
        if ($from) {
            $this->query->where($column, '>=', $from);
        }
        if ($to) {
            $this->query->where($column, '<=', $to . ' 23:59:59');
        }
        return $this;
    }

    public function applyStatus(?string $status, string $column = 'status'): self
    {
        if ($status && $status !== 'all') {
            $this->query->where($column, $status);
        }
        return $this;
    }

    public function applySearch(?string $search, array $columns): self
    {
        if ($search) {
            $this->query->where(function (Builder $q) use ($search, $columns) {
                foreach ($columns as $i => $col) {
                    if ($i === 0) {
                        $q->where($col, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($col, 'like', "%{$search}%");
                    }
                }
            });
        }
        return $this;
    }

    public function applySort(?string $sort, ?string $dir): self
    {
        $column = in_array($sort, $this->allowedSorts) ? $sort : $this->defaultSort;
        $direction = in_array(strtolower($dir ?? ''), ['asc', 'desc']) ? $dir : $this->defaultSortDir;
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function paginate(int $perPage = 50)
    {
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 50;
        return $this->query->paginate($perPage);
    }

    public function get()
    {
        return $this->query->get();
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }
}