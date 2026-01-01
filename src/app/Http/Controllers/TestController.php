<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTO\TestDTO;
use App\Http\Requests\CreateRequest;
use App\Http\Requests\UpdateRequest;
use App\Http\Resources\FailResource;
use App\Http\Resources\SuccessResource;
use App\Http\Resources\TestResource;
use App\Services\TestService;
use Illuminate\Http\Resources\Json\JsonResource;

final readonly class TestController
{
    public function __construct(private TestService $testService)
    {
    }

    public function create(CreateRequest $request): JsonResource
    {
        $type = $this->testService->create(TestDTO::from($request->validated()));

        return is_null($type) ? TestResource::make([]) : TestResource::make($type);
    }

    public function update(UpdateRequest $request): JsonResource
    {
        return $this->testService->update(TestDTO::from($request->validated()))
            ? SuccessResource::make([])
            : FailResource::make([]);
    }
}
