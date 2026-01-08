@extends('cockpit.layout')

@section('title', 'Главная страница')
@section('page-title', 'Главная')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Главная</h2>
      <div class="text-secondary">Сводка и ключевые метрики</div>
    </div>
  </div>
  <div id="dash-stats" class="row row-deck row-cards mt-2"
       data-fetch-url="{{ route('cockpit.dashboard.stats.partial') }}"
       data-fetch-interval="8000">
    @include('cockpit.partials.dashboard_stats')
  </div>
</div>

<div class="row row-cards">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Новые пользователи (30 дней)</h3>
      </div>
      <div class="card-body">
        <div class="chart" style="height: 280px">
          <canvas id="newUsersChart"></canvas>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">
        <h3 class="card-title">Новые ключи (30 дней)</h3>
      </div>
      <div class="card-body">
        <div class="chart" style="height: 280px">
          <canvas id="newKeysChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Недавние транзакции</h3>
      </div>
      <div class="card-body">
        @if($recentTransactions->count() > 0)
        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr>
                <th>Пользователь</th>
                <th>Хост</th>
                <th>План</th>
                <th>Цена</th>
                <th>Дата</th>
              </tr>
            </thead>
            <tbody id="dash-transactions"
                   data-fetch-url="{{ route('cockpit.dashboard.transactions.partial', ['page' => 1]) }}"
                   data-fetch-interval="10000">
              @include('cockpit.partials.dashboard_transactions', ['transactions' => $recentTransactions])
            </tbody>
          </table>
        </div>
        @else
        <p class="text-secondary">Пока нет транзакций для отображения.</p>
        @endif
      </div>
    </div>
  </div>
</div>

<script id="chart-data" type="application/json">
  @json($chartData)
</script>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const CHART_DATA = JSON.parse(document.getElementById('chart-data').textContent);
  
  function prepareChartData(data, label, color) {
    const labels = [];
    const values = [];
    const today = new Date();

    for (let i = 29; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(today.getDate() - i);
      const dateString = date.toISOString().split('T')[0];
      const formattedDate = `${date.getDate().toString().padStart(2,'0')}.${(date.getMonth()+1).toString().padStart(2,'0')}`;
      labels.push(formattedDate);
      values.push(data[dateString] || 0);
    }

    return {
      labels: labels,
      datasets: [{
        label: label,
        data: values,
        borderColor: color,
        backgroundColor: color + '33',
        borderWidth: 2,
        fill: true,
        tension: 0.3,
      }],
    };
  }

  // Users chart
  const usersChartCanvas = document.getElementById('newUsersChart');
  if (usersChartCanvas && typeof Chart !== 'undefined') {
    const usersCtx = usersChartCanvas.getContext('2d');
    const usersChartData = prepareChartData(CHART_DATA.users, 'Новые пользователи', '#007bff');
    const usersChart = new Chart(usersCtx, {
      type: 'line',
      data: usersChartData,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0,
            }
          },
          x: {
            ticks: {
              maxTicksLimit: 15,
            }
          }
        },
        plugins: {
          legend: {
            display: true,
          }
        }
      }
    });

    // Auto refresh charts
    setInterval(async function() {
      try {
        const resp = await fetch('{{ route('cockpit.dashboard.charts.json') }}', {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store'
        });
        if (!resp.ok) return;
        const fresh = await resp.json();
        if (!fresh) return;
        const newUsers = prepareChartData(fresh.users, 'Новые пользователи', '#007bff');
        usersChart.data.labels = newUsers.labels;
        usersChart.data.datasets[0].data = newUsers.datasets[0].data;
        usersChart.update('none');
      } catch(_) {}
    }, 10000);
  }

  // Keys chart
  const keysChartCanvas = document.getElementById('newKeysChart');
  if (keysChartCanvas && typeof Chart !== 'undefined') {
    const keysCtx = keysChartCanvas.getContext('2d');
    const keysChartData = prepareChartData(CHART_DATA.keys, 'Новые ключи', '#28a745');
    const keysChart = new Chart(keysCtx, {
      type: 'line',
      data: keysChartData,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0,
            }
          },
          x: {
            ticks: {
              maxTicksLimit: 15,
            }
          }
        },
        plugins: {
          legend: {
            display: true,
          }
        }
      }
    });

    // Auto refresh charts
    setInterval(async function() {
      try {
        const resp = await fetch('{{ route('cockpit.dashboard.charts.json') }}', {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store'
        });
        if (!resp.ok) return;
        const fresh = await resp.json();
        if (!fresh) return;
        const newKeys = prepareChartData(fresh.keys, 'Новые ключи', '#28a745');
        keysChart.data.labels = newKeys.labels;
        keysChart.data.datasets[0].data = newKeys.datasets[0].data;
        keysChart.update('none');
      } catch(_) {}
    }, 10000);
  }
});
</script>
@endpush
@endsection
