<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\SettingDTO;
use App\Models\Setting;
use App\Repositories\SettingRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class SettingService
{
    public function __construct(
        private SettingRepository $settingRepository,
        private CacheRepository $cache,
    ) {
    }

    /**
     * Получить настройку по ключу с приведением типа.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        /** @var Setting|null $setting */
        $setting = $this->cache->rememberForever(
            $this->cacheKey($key),
            fn () => $this->settingRepository->findByKey($key)
        );

        if ($setting === null) {
            return $default;
        }

        return $setting->getTypedValue();
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return (bool) $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return (int) $value;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);

        return $value === null ? null : (string) $value;
    }

    /**
     * Создание/обновление настройки (используется в админке).
     */
    public function upsert(SettingDTO $dto): Setting
    {
        /** @var Setting|null $existing */
        $existing = $dto->id !== null
            ? $this->settingRepository->findById($dto->id)
            : $this->settingRepository->findByKey($dto->key);

        if ($existing instanceof Setting) {
            $existing->fill([
                'key' => $dto->key,
                'type' => $dto->type,
                'group' => $dto->group,
                'description' => $dto->description,
                'is_system' => $dto->is_system,
            ]);
            $existing->setTypedValue($dto->value);
            $existing->save();

            $this->forgetCache($dto->key);

            return $existing;
        }

        $setting = new Setting([
            'key' => $dto->key,
            'type' => $dto->type,
            'group' => $dto->group,
            'description' => $dto->description,
            'is_system' => $dto->is_system,
        ]);
        $setting->setTypedValue($dto->value);
        $setting->save();

        $this->forgetCache($dto->key);

        return $setting;
    }

    public function delete(int $id): bool
    {
        /** @var Setting|null $setting */
        $setting = $this->settingRepository->findById($id);

        if ($setting === null) {
            return false;
        }

        $deleted = $this->settingRepository->delete($id);

        if ($deleted) {
            $this->forgetCache($setting->key);
        }

        return $deleted;
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->settingRepository->getAllPaginated($perPage);
    }

    private function cacheKey(string $key): string
    {
        return 'settings.' . $key;
    }

    private function forgetCache(string $key): void
    {
        $this->cache->forget($this->cacheKey($key));
    }
}


