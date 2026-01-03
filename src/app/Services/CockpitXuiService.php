<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\XuiDTO;
use App\Models\Xui;
use App\Repositories\XuiRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class CockpitXuiService
{
    public function __construct(private XuiRepository $xuiRepository)
    {
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->xuiRepository->getAllPaginated($perPage);
    }

    public function findById(int $id): ?Xui
    {
        /** @var Xui|null */
        return $this->xuiRepository->findById($id);
    }

    public function create(XuiDTO $xuiDTO): ?Xui
    {
        /** @var Xui|null */
        return $this->xuiRepository->create($xuiDTO);
    }

    public function update(XuiDTO $xuiDTO): bool
    {
        return $this->xuiRepository->update($xuiDTO);
    }

    public function delete(int $id): bool
    {
        return $this->xuiRepository->delete($id);
    }
}

