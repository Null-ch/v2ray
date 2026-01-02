<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\ReferralDTO;
use App\Models\Referral;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class ReferralRepository extends BaseRepository
{
    public function __construct(Referral $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function create(ReferralDTO $referralDTO): ?Model
    {
        try {
            return DB::transaction(function () use ($referralDTO) {
                return $this->createInstance($referralDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Referral create transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return null;
        }
    }

    public function update(ReferralDTO $referralDTO): bool
    {
        try {
            return DB::transaction(function () use ($referralDTO) {
                return $this->updateInstance($referralDTO);
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Referral update transaction error: ' . $exception->getMessage());
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
            $this->logger->error('Referral delete transaction error: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            return false;
        }
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->with(['user', 'referredUser'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Model
    {
        return $this->model
            ->newQuery()
            ->with(['user', 'referredUser'])
            ->where('id', $id)
            ->first();
    }
}

