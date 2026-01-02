<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Xui;
use Illuminate\Database\Eloquent\Model;
use Psr\Log\LoggerInterface;

class XuiRepository extends BaseRepository
{
    public function __construct(Xui $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function findByTag(string $tag): ?Model
    {
        return $this->model
            ->newQuery()
            ->where('tag', $tag)
            ->where('is_active', true)
            ->first();
    }
}

