<?php

namespace App\Services\User;

use App\DTO\User\UserDTO;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Str;

final readonly class UserService
{
    private const DAILY_COST = 2;
    private const INITIAL_BALANCE = 14; // 7 дней

    public function __construct(private UserRepository $userRepository)
    {
    }

    public function findUserByTelegramId(int $telegramId): ?User
    {
        $user = $this->userRepository->findByTelegramId($telegramId);

        $user?->load(['balance', 'configurations']);

        return $user;
    }

    public function createUser(int $telegramId, ?string $username = null, ?string $name = null): ?User
    {
        // Проверяем, не существует ли уже пользователь с таким telegram_id
        $existingUser = $this->findUserByTelegramId($telegramId);
        if ($existingUser) {
            \Illuminate\Support\Facades\Log::warning('User already exists', [
                'telegram_id' => $telegramId,
                'user_id' => $existingUser->id,
            ]);
            return $existingUser;
        }

        try {
            $userDTO = UserDTO::from([
                'tg_id' => $telegramId,
                'tg_tag' => $username,
                'name' => $name,
                'uuid' => Str::uuid()->toString(),
                'is_active' => true,
                'referral_code' => Str::random(10),
            ]);

            \Illuminate\Support\Facades\Log::info('Creating user with DTO', [
                'telegram_id' => $telegramId,
                'username' => $username,
                'name' => $name,
            ]);

            $user = $this->userRepository->createInstance($userDTO);

            if (!$user) {
                \Illuminate\Support\Facades\Log::error('Failed to create user instance', [
                    'telegram_id' => $telegramId,
                ]);
                return null;
            }

            \Illuminate\Support\Facades\Log::info('User instance created', [
                'user_id' => $user->id,
                'telegram_id' => $telegramId,
            ]);

            // Создаем баланс для пользователя
            try {
                $user->balance()->create([
                    'balance' => self::INITIAL_BALANCE,
                ]);
                \Illuminate\Support\Facades\Log::info('Balance created for user', [
                    'user_id' => $user->id,
                    'balance' => self::INITIAL_BALANCE,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to create balance for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Продолжаем, даже если баланс не создан - пользователь создан
            }

            /** @var User */
            return $user->fresh(['balance']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error in createUser', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    public function generateVpnConfig(): string
    {
        // Заглушка для генерации конфига
        return 'VPN_CONFIG_PLACEHOLDER';
    }

    public function getDailyCost(): int
    {
        return self::DAILY_COST;
    }

    public function getInitialBalance(): int
    {
        return self::INITIAL_BALANCE;
    }
}
