<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Pricing;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Xui;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class CockpitController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/cockpit';

    /**
     * Show the login form.
     */
    public function showLoginForm(): View
    {
        return view('cockpit.login');
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('cockpit');
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username(): string
    {
        return 'username';
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user): RedirectResponse
    {
        return redirect()->route('cockpit.dashboard');
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->guard()->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return $this->loggedOut($request) ?: redirect()->route('cockpit.login');
    }

    /**
     * Show the dashboard.
     */
    public function dashboard(): View
    {
        $totalUsers = User::count();
        $activeUsers = Subscription::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->distinct('user_id')
            ->count('user_id');

        $totalSubscriptions = Subscription::count();

        $totalXui = Xui::count();
        $activeXui = Xui::query()->where('is_active', true)->count();

        $totalPayments = Payment::count();
        $succeededPayments = Payment::query()
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->count();
        $totalRevenue = Payment::query()
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->sum('amount');
        $todayRevenue = Payment::query()
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        // Ежедневная выручка за последние 7 дней
        $dailyRevenue = Payment::query()
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total' => (float) $row->total,
            ]);

        // Разбивка по тарифам (по pricing_id в metadata)
        $pricingStats = Payment::query()
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.pricing_id")) as pricing_id_raw, COUNT(*) as payments_count, SUM(amount) as total_amount')
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->whereRaw('JSON_EXTRACT(metadata, "$.pricing_id") IS NOT NULL')
            ->groupBy('pricing_id_raw')
            ->get()
            ->map(function ($row) {
                return [
                    'pricing_id' => (int) $row->pricing_id_raw,
                    'payments_count' => (int) $row->payments_count,
                    'total_amount' => (float) $row->total_amount,
                ];
            });

        $pricingLabels = [];
        $pricingAmounts = [];

        if ($pricingStats->isNotEmpty()) {
            $pricingIds = $pricingStats->pluck('pricing_id')->all();
            $pricings = Pricing::query()
                ->whereIn('id', $pricingIds)
                ->get()
                ->keyBy('id');

            foreach ($pricingStats as $stat) {
                /** @var \App\Models\Pricing|null $pricing */
                $pricing = $pricings->get($stat['pricing_id']);
                $label = $pricing?->title ?? ('Тариф #' . $stat['pricing_id']);

                $pricingLabels[] = $label;
                $pricingAmounts[] = $stat['total_amount'];
            }
        }

        return view('cockpit.dashboard', compact(
            'totalUsers',
            'activeUsers',
            'totalSubscriptions',
            'totalXui',
            'activeXui',
            'totalPayments',
            'succeededPayments',
            'totalRevenue',
            'todayRevenue',
            'dailyRevenue',
            'pricingLabels',
            'pricingAmounts',
        ));
    }
}
