<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\XuiDTO;
use App\Models\Xui;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class XuiRepository extends BaseRepository
{
    public function __construct(Xui $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function findByTag(string $tag = 'NL'): ?Model
    {
        return $this->model
            ->newQuery()
            ->where('tag', $tag)
            ->where('is_active', true)
            ->first();
    }

    public function create(XuiDTO $xuiDTO): ?Model
    {
        try {
            return DB::transaction(function () use ($xuiDTO) {
                return $this->createInstance($xuiDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('XUI create transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return null;
        }
    }

    public function update(XuiDTO $xuiDTO): bool
    {
        try {
            return DB::transaction(function () use ($xuiDTO) {
                // Фильтруем null значения, чтобы не обновлять поля, которые не были переданы
                $data = array_filter($xuiDTO->toArray(), function ($value) {
                    return $value !== null;
                });
                
                // Убеждаемся, что id присутствует для обновления
                if (!isset($data['id'])) {
                    return false;
                }
                
                $id = $data['id'];
                unset($data['id']);
                
                // Используем модель Eloquent вместо query builder, чтобы применялись casts (например, шифрование пароля)
                $xui = $this->model->find($id);
                if (!$xui) {
                    return false;
                }
                
                $xui->fill($data);
                return $xui->save();
            });
        } catch (\Throwable $exception) {
            $this->logger->error('XUI update transaction error: ' . $exception->getMessage());
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
            $this->logger->error('XUI delete transaction error: ' . $exception->getMessage());
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

    public function findById(int $id): ?Model
    {
        return $this->showInstance($id);
    }
}

