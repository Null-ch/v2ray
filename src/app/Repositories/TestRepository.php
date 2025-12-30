<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\TestDTO;
use App\Models\Type;
use Illuminate\Database\Eloquent\Model;
use Psr\Log\LoggerInterface;

class TestRepository extends BaseRepository
{
    public function __construct(Type $model, LoggerInterface $logger)
    {
        parent::__construct($model, $logger);
    }

    public function create(TestDTO $testDTO): ?Model
    {
        return parent::createInstance($testDTO);
    }
}
