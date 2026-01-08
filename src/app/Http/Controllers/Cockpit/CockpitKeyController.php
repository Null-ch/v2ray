<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cockpit;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Xui;
use App\Services\CockpitSubscriptionService;
use App\Services\XuiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CockpitKeyController extends Controller
{
    public function __construct(
        private CockpitSubscriptionService $subscriptionService,
        private XuiService $xuiService,
    ) {
    }

    /**
     * Display a listing of keys.
     */
    public function index(Request $request): View
    {
        $userId = $request->query('user_id');
        
        $query = Subscription::query()
            ->with(['user', 'xui'])
            ->orderBy('created_at', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $keys = $query->paginate(15);
        $users = User::all();
        $xuis = Xui::where('is_active', true)->get();

        return view('cockpit.key.index', compact('keys', 'users', 'xuis'));
    }

    /**
     * Store a newly created key.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'xui_id' => 'required|integer|exists:xuis,id',
            'expiry_date' => 'nullable|date',
            'email' => 'nullable|string|max:255',
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $xui = Xui::findOrFail($validated['xui_id']);

            // Генерируем UUID если его нет
            if (!$user->uuid) {
                $user->uuid = \Illuminate\Support\Str::uuid()->toString();
                $user->save();
            }

            // Генерируем email если не указан
            $email = $validated['email'] ?? $user->getVpnEmail() . '@bot.local';

            // Вычисляем expiry в миллисекундах
            $expiryMs = null;
            if ($validated['expiry_date']) {
                $expiryMs = \Carbon\Carbon::parse($validated['expiry_date'])->timestamp * 1000;
            }

            // Получаем первый активный inbound для XUI
            $inbounds = $this->xuiService->getInbounds($xui->tag);
            $inboundId = null;
            if (!empty($inbounds['data'])) {
                $inboundId = $inbounds['data'][0]['id'] ?? null;
            }

            if (!$inboundId) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Не найден активный inbound на сервере XUI',
                ], 500);
            }

            // Создаем клиента на XUI
            $clientData = [
                'email' => $email,
                'id' => $user->uuid,
                'enable' => true,
            ];

            if ($expiryMs) {
                $clientData['expiryTime'] = $expiryMs;
            }

            $result = $this->xuiService->addClient($xui->tag, $inboundId, $clientData, $user->id);

            if (!$result['ok']) {
                return response()->json([
                    'ok' => false,
                    'error' => $result['response']['msg'] ?? 'Ошибка создания ключа на сервере',
                ], 500);
            }

            // Создаем подписку в БД
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'xui_id' => $xui->id,
                'uuid' => $user->uuid,
                'expires_at' => $validated['expiry_date'] ? \Carbon\Carbon::parse($validated['expiry_date']) : null,
            ]);

            return response()->json([
                'ok' => true,
                'key_id' => $subscription->id,
                'uuid' => $user->uuid,
                'expiry_ms' => $expiryMs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate email for user.
     */
    public function generateEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $baseEmail = $user->getVpnEmail() . '@bot.local';

        // Проверяем уникальность
        $counter = 1;
        $email = $baseEmail;
        while (Subscription::where('uuid', $user->uuid)->exists()) {
            $email = str_replace('@bot.local', "-{$counter}@bot.local", $baseEmail);
            $counter++;
        }

        return response()->json([
            'ok' => true,
            'email' => $email,
        ]);
    }

    /**
     * Delete a key.
     */
    public function destroy(int $id): RedirectResponse
    {
        $subscription = Subscription::with(['user', 'xui'])->findOrFail($id);

        try {
            // Удаляем с сервера XUI
            try {
                $inbounds = $this->xuiService->getInbounds($subscription->xui->tag);
                if (!empty($inbounds['data'])) {
                    $inboundId = $inbounds['data'][0]['id'] ?? null;
                    if ($inboundId) {
                        $client = $this->xuiService->getXuiApiClient($subscription->xui->tag);
                        $client->removeClient($inboundId, $subscription->uuid);
                    }
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to delete key from XUI server: {$e->getMessage()}");
            }
        } catch (\Exception $e) {
            // Логируем ошибку, но продолжаем удаление из БД
            \Log::warning("Failed to delete key from XUI server: {$e->getMessage()}");
        }

        $subscription->delete();

        return redirect()
            ->route('cockpit.key.index')
            ->with('success', 'Ключ успешно удален.');
    }

    /**
     * Adjust key expiry.
     */
    public function adjustExpiry(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'delta_days' => 'required|integer|min:-3650|max:3650',
        ]);

        $subscription = Subscription::with(['user', 'xui'])->findOrFail($id);
        $deltaDays = $validated['delta_days'];

        try {
            $currentExpiry = $subscription->expires_at 
                ? \Carbon\Carbon::parse($subscription->expires_at)
                : \Carbon\Carbon::now();

            $newExpiry = $currentExpiry->addDays($deltaDays);
            $newExpiryMs = $newExpiry->timestamp * 1000;

            // Обновляем на сервере XUI
            $inbounds = $this->xuiService->getInbounds($subscription->xui->tag);
            if (!empty($inbounds['data'])) {
                $inboundId = $inbounds['data'][0]['id'] ?? null;
                if ($inboundId) {
                    $clientData = [
                        'id' => $subscription->uuid,
                        'expiryTime' => $newExpiryMs,
                    ];
                    $result = $this->xuiService->updateClient(
                        $subscription->xui->tag,
                        $inboundId,
                        $subscription->uuid,
                        $clientData
                    );

                    if (!$result['ok']) {
                        return response()->json([
                            'ok' => false,
                            'error' => 'Не удалось обновить срок на сервере',
                        ], 500);
                    }
                }
            }

            // Обновляем в БД
            $subscription->expires_at = $newExpiry;
            $subscription->save();

            return response()->json([
                'ok' => true,
                'new_expiry_ms' => $newExpiryMs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sweep expired keys.
     */
    public function sweepExpired(): RedirectResponse
    {
        $expiredKeys = Subscription::where('expires_at', '<', now())->get();
        $removed = 0;
        $failed = 0;

        foreach ($expiredKeys as $subscription) {
            try {
                // Удаляем с сервера
                try {
                    $inbounds = $this->xuiService->getInbounds($subscription->xui->tag);
                    if (!empty($inbounds['data'])) {
                        $inboundId = $inbounds['data'][0]['id'] ?? null;
                        if ($inboundId) {
                            $client = $this->xuiService->getXuiApiClient($subscription->xui->tag);
                            $client->removeClient($inboundId, $subscription->uuid);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to delete key from XUI server: {$e->getMessage()}");
                }
                $subscription->delete();
                $removed++;
            } catch (\Exception $e) {
                $failed++;
                \Log::warning("Failed to delete expired key {$subscription->id}: {$e->getMessage()}");
            }
        }

        $message = "Удалено истёкших ключей: {$removed}. Ошибок: {$failed}.";
        $category = $failed === 0 ? 'success' : 'warning';

        return redirect()
            ->route('cockpit.key.index')
            ->with($category, $message);
    }
}

