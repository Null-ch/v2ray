<div class="col-sm-6 col-lg-3">
  <div class="card">
    <div class="card-body">
      <div class="subheader">Пользователей</div>
      <div class="h1 mb-0">{{ $stats['user_count'] ?? 0 }}</div>
    </div>
  </div>
</div>
<div class="col-sm-6 col-lg-3">
  <div class="card">
    <div class="card-body">
      <div class="subheader">Всего ключей</div>
      <div class="h1 mb-0">{{ $stats['total_keys'] ?? 0 }}</div>
    </div>
  </div>
</div>
<div class="col-sm-6 col-lg-3">
  <div class="card">
    <div class="card-body">
      <div class="subheader">Всего потрачено</div>
      <div class="h1 mb-0">{{ number_format($stats['total_spent'] ?? 0, 2) }} <small class="text-secondary">RUB</small></div>
    </div>
  </div>
</div>
<div class="col-sm-6 col-lg-3">
  <div class="card">
    <div class="card-body">
      <div class="subheader">Активных хостов</div>
      <div class="h1 mb-1">{{ $stats['host_count'] ?? 0 }}</div>
    </div>
  </div>
</div>

