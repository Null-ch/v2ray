<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\PricingDTO;
use App\Models\Pricing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class PricingRepository extends BaseRepository
{
    public function __construct(Pricing $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function create(PricingDTO $pricingDTO): ?Model
    {
        try {
            return DB::transaction(function () use ($pricingDTO) {
                return $this->createInstance($pricingDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Pricing create transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return null;
        }
    }

    public function update(PricingDTO $pricingDTO): bool
    {
        try {
            return DB::transaction(function () use ($pricingDTO) {
                // Фильтруем null значения, чтобы не обновлять поля, которые не были переданы
                $data = array_filter($pricingDTO->toArray(), function ($value) {
                    return $value !== null;
                });
                
                // Убеждаемся, что id присутствует для обновления
                if (!isset($data['id'])) {
                    return false;
                }
                
                $id = $data['id'];
                unset($data['id']);
                
                $pricing = $this->model->find($id);
                if (!$pricing) {
                    return false;
                }
                
                $pricing->fill($data);
                return $pricing->save();
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Pricing update transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            return DB::transaction(function () use ($id) {
                return $this->deleteInstance($id);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Pricing delete transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return false;
        }
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getAll(): Collection
    {
        return $this->model
            ->newQuery()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findById(int $id): ?Model
    {
        return $this->showInstance($id);
    }
}

