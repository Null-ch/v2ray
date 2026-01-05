<?php

namespace App\Services\User;

use App\DTO\User\UserDTO;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Str;

final readonly class UserService
{
    private const DAILY_COST = 2;
    private const MONTHLY_COST = 80;
    private const INITIAL_BALANCE = 0;

    public function __construct(private UserRepository $userRepository) {}

    public function findUserByTelegramId(int $telegramId): ?User
    {
        $user = $this->userRepository->findByTelegramId($telegramId);
        $user?->load(['balance', 'configurations']);

        return $user;
    }

    public function findUserByReferralCode(string $referralCode): ?User
    {
        $user = $this->userRepository->findByReferralCode($referralCode);
        return $user;
    }

    public function createUser(int $telegramId, ?string $username = null, ?string $name = null, ?int $referrerId = null): ?User
    {
        $userDTO = UserDTO::from([
            'tg_id' => $telegramId,
            'tg_tag' => $username,
            'name' => $name,
            'uuid' => Str::uuid()->toString(),
            'is_active' => true,
            'referral_code' => Str::random(10),
            'referrer_id' => $referrerId,
        ]);

        $user = $this->userRepository->createInstance($userDTO);

        if (!$user) {
            return null;
        }

        $user->balance()->create([
            'balance' => self::INITIAL_BALANCE,
        ]);

        /** @var User */
        return $user->fresh(['balance']);
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

    public function getMonthlyCost(): int
    {
        return self::MONTHLY_COST;
    }

    public function getInitialBalance(): int
    {
        return self::INITIAL_BALANCE;
    }
}
