<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\SettingDTO;
use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

final class SettingRepository extends BaseRepository
{
    public function __construct(Setting $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function findByKey(string $key): ?Model
    {
        return $this->model
            ->newQuery()
            ->where('key', $key)
            ->first();
    }

    public function create(SettingDTO $settingDTO): ?Model
    {
        try {
            return DB::transaction(function () use ($settingDTO) {
                return $this->createInstance($settingDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Setting create transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return null;
        }
    }

    public function update(SettingDTO $settingDTO): bool
    {
        try {
            return DB::transaction(function () use ($settingDTO) {
                return $this->updateInstance($settingDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Setting update transaction error: ' . $exception->getMessage());
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
            $this->logger->error('Setting delete transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return false;
        }
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->orderBy('group')
            ->orderBy('key')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Model
    {
        return $this->showInstance($id);
    }
}


