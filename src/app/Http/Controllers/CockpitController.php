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

        $pricingTableData = [];

        if ($pricingStats->isNotEmpty()) {
            $pricingIds = $pricingStats->pluck('pricing_id')->all();
            $pricings = Pricing::query()
                ->whereIn('id', $pricingIds)
                ->get()
                ->keyBy('id');

            foreach ($pricingStats as $stat) {
                /** @var \App\Models\Pricing|null $pricing */
                $pricing = $pricings->get($stat['pricing_id']);
                $pricingTableData[] = [
                    'title' => $pricing?->title ?? ('Тариф #' . $stat['pricing_id']),
                    'payments_count' => $stat['payments_count'],
                    'total_amount' => $stat['total_amount'],
                ];
            }
        }

        // Данные для графиков (последние 30 дней)
        $chartData = $this->getDailyStatsForCharts(30);
        
        // Недавние транзакции
        $recentTransactions = Payment::query()
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

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
            'pricingTableData',
            'chartData',
            'recentTransactions',
        ));
    }

    /**
     * Get daily stats for charts.
     */
    private function getDailyStatsForCharts(int $days = 30): array
    {
        $usersData = [];
        $keysData = [];
        
        $startDate = now()->subDays($days);
        
        // Новые пользователи по дням
        $newUsers = User::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->keyBy('date');
        
        // Новые ключи (subscriptions) по дням
        $newKeys = Subscription::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->keyBy('date');
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $usersData[$date] = $newUsers->get($date)?->count ?? 0;
            $keysData[$date] = $newKeys->get($date)?->count ?? 0;
        }
        
        return [
            'users' => $usersData,
            'keys' => $keysData,
        ];
    }

    /**
     * Partial: dashboard stats.
     */
    public function dashboardStatsPartial(): View
    {
        $totalUsers = User::count();
        $totalKeys = Subscription::count();
        $totalRevenue = Payment::query()
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->sum('amount');
        $totalXui = Xui::where('is_active', true)->count();
        
        $stats = [
            'user_count' => $totalUsers,
            'total_keys' => $totalKeys,
            'total_spent' => $totalRevenue,
            'host_count' => $totalXui,
        ];
        
        return view('cockpit.partials.dashboard_stats', compact('stats'));
    }

    /**
     * Partial: dashboard transactions.
     */
    public function dashboardTransactionsPartial(Request $request): View
    {
        $page = $request->query('page', 1);
        $perPage = 8;
        
        $transactions = Payment::query()
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        
        return view('cockpit.partials.dashboard_transactions', compact('transactions'));
    }

    /**
     * JSON: dashboard charts data.
     */
    public function dashboardChartsJson(): \Illuminate\Http\JsonResponse
    {
        $data = $this->getDailyStatsForCharts(30);
        return response()->json($data);
    }
}
