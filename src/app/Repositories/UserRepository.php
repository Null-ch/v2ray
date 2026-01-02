<?php

namespace App\Repositories;

use App\Models\User;
use Psr\Log\LoggerInterface;

class UserRepository extends BaseRepository
{
    public function __construct(User $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function findByTelegramId(int $telegramId): ?User
    {
        return $this->model
            ->newQuery()
            ->where('tg_id', $telegramId)
            ->first();
    }
}
