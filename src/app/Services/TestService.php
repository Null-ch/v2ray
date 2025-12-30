<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\TestDTO;
use App\Models\Type;
use App\Repositories\TestRepository;

final readonly class TestService
{
    public function __construct(private TestRepository $testRepository)
    {
    }

    public function create(TestDTO $testDTO): ?Type
    {
        /** @var Type|null */
        return $this->testRepository->create($testDTO);
    }
}
