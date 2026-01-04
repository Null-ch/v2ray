<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\UserTagDTO;
use App\Enums\XuiTag;
use App\Models\UserTag;
use App\Repositories\UserTagRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final readonly class UserTagService
{
    public function __construct(private UserTagRepository $userTagRepository)
    {
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userTagRepository->getAllPaginated($perPage);
    }

    public function findById(int $id): ?UserTag
    {
        /** @var UserTag|null */
        return $this->userTagRepository->findById($id);
    }

    public function findByUserId(int $userId): Collection
    {
        return $this->userTagRepository->findByUserId($userId);
    }

    public function findByUserIdAndTag(int $userId, XuiTag $tag): ?UserTag
    {
        /** @var UserTag|null */
        return $this->userTagRepository->findByUserIdAndTag($userId, $tag->value);
    }

    public function create(UserTagDTO $userTagDTO): ?UserTag
    {
        /** @var UserTag|null */
        return $this->userTagRepository->create($userTagDTO);
    }

    public function update(UserTagDTO $userTagDTO): bool
    {
        return $this->userTagRepository->update($userTagDTO);
    }

    public function delete(int $id): bool
    {
        return $this->userTagRepository->delete($id);
    }

    public function addTagToUser(int $userId, XuiTag $tag): ?UserTag
    {
        // Проверяем, существует ли уже такая связь
        $existing = $this->findByUserIdAndTag($userId, $tag);
        if ($existing) {
            return $existing;
        }

        $userTagDTO = UserTagDTO::from([
            'user_id' => $userId,
            'tag' => $tag,
        ]);

        return $this->create($userTagDTO);
    }

    public function removeTagFromUser(int $userId, XuiTag $tag): bool
    {
        $userTag = $this->findByUserIdAndTag($userId, $tag);
        if (!$userTag) {
            return false;
        }

        return $this->delete($userTag->id);
    }
}

