<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\BaseItemDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Psr\Log\LoggerInterface;

abstract class BaseRepository
{
    protected Builder $builder;

    public function __construct(
        public Model $model,
        public LoggerInterface $logger
    ) {
    }

    public final function createInstance(BaseItemDTO $itemDTO): Model|null
    {
        try {
            return $this->model
                ->newQuery()
                ->findOrCreate($itemDTO->toArray());
        } catch (\Throwable $exception) {
            $this->logger->error('Entity create error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());

            return null;
        }
    }

    public final function updateInstance(BaseItemDTO $itemDTO): bool
    {
        return (bool)$this->model
            ->newQuery()
            ->where('id', $itemDTO->id)
            ->update($itemDTO->toArray());
    }

    public final function deleteInstance(int $id): bool
    {
        return (bool)$this->model
            ->newQuery()
            ->where('id', $id)
            ->delete();
    }

    public final function showInstance(int $id): Model|null
    {
        return $this->model
            ->newQuery()
            ->where('id', $id)
            ->first();
    }
}
