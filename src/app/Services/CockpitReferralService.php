<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\ReferralDTO;
use App\Models\Referral;
use App\Repositories\ReferralRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class CockpitReferralService
{
    public function __construct(private ReferralRepository $referralRepository)
    {
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->referralRepository->getAllPaginated($perPage);
    }

    public function findById(int $id): ?Referral
    {
        /** @var Referral|null */
        return $this->referralRepository->findById($id);
    }

    public function create(ReferralDTO $referralDTO): ?Referral
    {
        /** @var Referral|null */
        return $this->referralRepository->create($referralDTO);
    }

    public function update(ReferralDTO $referralDTO): bool
    {
        return $this->referralRepository->update($referralDTO);
    }

    public function delete(int $id): bool
    {
        return $this->referralRepository->delete($id);
    }
}

