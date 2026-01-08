@extends('cockpit.layout')

@section('title', 'Управление Пользователями')
@section('page-title', 'Пользователи')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Пользователи</h2>
      <div class="text-secondary">Список зарегистрированных пользователей и быстрые действия</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
      <div class="d-flex">
        <input id="users-search" type="text" class="form-control" placeholder="Поиск по ID или Username..." />
      </div>
    </div>
  </div>
</div>

<!-- Modal: Баланс пользователя -->
<div class="modal modal-blur fade" id="balanceModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Баланс пользователя</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="bm-close-btn"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 text-secondary" id="bm-user"></div>
        <div class="mb-2">Текущий баланс: <strong id="bm-balance">0.00</strong> RUB</div>
        <form id="bm-form" class="d-flex gap-2" method="post">
          @csrf
          <input class="form-control" type="text" inputmode="decimal" name="delta" placeholder="± сумма" required id="bm-delta-input" />
          <button class="btn btn-primary" type="submit" id="bm-submit-btn">
            <span class="btn-text">Изменить</span>
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table id="users-table" class="table table-vcenter">
        <thead>
          <tr>
            <th>ID</th>
            <th>Имя</th>
            <th>TG ID</th>
            <th>Активные ключи</th>
            <th>Баланс</th>
            <th class="w-1">Действия</th>
          </tr>
        </thead>
        <tbody id="users-tbody" data-fetch-url="{{ route('cockpit.users.table.partial') }}" data-fetch-interval="10000">
          @forelse($users as $user)
          <tr>
            <td class="user-id">{{ $user->id }}</td>
            <td class="user-name">{{ $user->name }}</td>
            <td>{{ $user->tg_id }}</td>
            <td>
              {{ $user->subscriptions->count() }} шт.
            </td>
            <td>{{ number_format($user->balance->balance ?? 0, 2) }}</td>
            <td>
              <div class="btn-list">
                <a class="btn btn-primary btn-sm btn-glass" href="{{ route('cockpit.key.index', ['user_id' => $user->id]) }}" title="Выдать ключ">
                  <i class="ti ti-key-plus"></i>
                </a>
                <button type="button"
                        class="btn btn-success btn-sm btn-glass btn-user-balance-sign"
                        data-uid="{{ $user->id }}"
                        data-username="{{ $user->name}}"
                        data-balance="{{ number_format($user->balance->balance ?? 0, 2) }}"
                        data-sign="+"
                        title="Начислить баланс">
                  <i class="ti ti-plus"></i>
                </button>
                <button type="button"
                        class="btn btn-danger btn-sm btn-glass btn-user-balance-sign"
                        data-uid="{{ $user->id }}"
                        data-username="{{ $user->name}}"
                        data-balance="{{ number_format($user->balance->balance ?? 0, 2) }}"
                        data-sign="-"
                        title="Списать баланс">
                  <i class="ti ti-minus"></i>
                </button>
                <a href="{{ route('cockpit.user.show', $user->id) }}" class="btn btn-info btn-sm btn-glass" title="Просмотр">
                  <i class="ti ti-eye"></i>
                </a>
                <a href="{{ route('cockpit.user.edit', $user->id) }}" class="btn btn-warning btn-sm btn-glass" title="Редактировать">
                  <i class="ti ti-edit"></i>
                </a>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center">Нет пользователей</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer clearfix">
    {{ $users->links() }}
  </div>
</div>

@push('scripts')
<script>
(function(){
  const input = document.getElementById('users-search');
  const table = document.getElementById('users-table');
  if (!input || !table) return;
  
  function norm(s){ return (s||'').toString().toLowerCase().trim(); }
  
  function applyFilter(){
    const q = norm(input.value);
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.forEach(tr => {
      const id = norm(tr.querySelector('.user-id')?.textContent);
      const name = norm(tr.querySelector('.user-name')?.textContent);
      const ok = !q || id.includes(q) || name.includes(q);
      tr.style.display = ok ? '' : 'none';
    });
  }
  
  input.addEventListener('input', applyFilter);
  input.addEventListener('keyup', applyFilter);

  // Открытие модалки баланса
  table.addEventListener('click', function(e){
    const btn = e.target.closest('.btn-user-balance-sign');
    if (!btn) return;
    const uid = btn.getAttribute('data-uid');
    const uname = btn.getAttribute('data-username') || 'N/A';
    const balance = btn.getAttribute('data-balance') || '0.00';
    const sign = btn.getAttribute('data-sign') || '';

    const modalEl = document.getElementById('balanceModal');
    const bmUser = modalEl.querySelector('#bm-user');
    const bmBal = modalEl.querySelector('#bm-balance');
    const bmForm = modalEl.querySelector('#bm-form');
    const deltaInput = bmForm.querySelector('input[name="delta"]');

    bmUser.textContent = `@${uname} (#${uid})`;
    bmBal.textContent = balance;
    bmForm.action = `{{ route('cockpit.user.balance.adjust', 0) }}`.replace('/0/', `/${uid}/`);

    try {
      deltaInput.value = sign;
      deltaInput.focus();
      const len = deltaInput.value.length;
      deltaInput.setSelectionRange(len, len);
    } catch(_){ }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  });

  // AJAX-сабмит формы изменения баланса
  const modalEl = document.getElementById('balanceModal');
  if (modalEl) {
    const form = modalEl.querySelector('#bm-form');
    const submitBtn = modalEl.querySelector('#bm-submit-btn');
    const submitBtnText = submitBtn?.querySelector('.btn-text');
    const submitBtnSpinner = submitBtn?.querySelector('.spinner-border');
    const closeBtn = modalEl.querySelector('#bm-close-btn');
    const deltaInput = modalEl.querySelector('#bm-delta-input');
    
    if (form) {
      form.addEventListener('submit', async function(ev){
        ev.preventDefault();
        ev.stopPropagation();
        
        // Disable form during submission
        if (submitBtn) {
          submitBtn.disabled = true;
          if (submitBtnText) submitBtnText.classList.add('d-none');
          if (submitBtnSpinner) submitBtnSpinner.classList.remove('d-none');
        }
        if (closeBtn) closeBtn.disabled = true;
        if (deltaInput) deltaInput.disabled = true;
        
        try{
          const fd = new FormData(form);
          const meta = document.querySelector('meta[name="csrf-token"]');
          const token = meta ? meta.getAttribute('content') : '';
          if (token) fd.append('_token', token);
          
          const resp = await fetch(form.action, {
            method: 'POST',
            body: fd,
            headers: { 
              'Accept': 'application/json', 
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': token
            },
            credentials: 'same-origin'
          });
          
          let data = null;
          try {
            const text = await resp.text();
            data = text ? JSON.parse(text) : null;
          } catch(e) {
            console.error('Failed to parse response:', e);
          }
          
          if (resp.ok && data && data.ok){
            try { window.showToast('success', data.message || 'Баланс изменён.'); } catch(_){ }
            try { 
              const modalInstance = bootstrap.Modal.getInstance(modalEl);
              if (modalInstance) modalInstance.hide();
            } catch(_){ }
            try { await window.refreshContainerById('users-tbody'); } catch(_){ }
          } else {
            const msg = (data && data.message) || (data && data.error) || 'Не удалось изменить баланс';
            try { window.showToast('danger', msg); } catch(_){ }
          }
        } catch(err){
          console.error('Request error:', err);
          try { window.showToast('danger', 'Ошибка сети: ' + (err.message || 'Неизвестная ошибка')); } catch(__){}
        } finally {
          // Re-enable form
          if (submitBtn) {
            submitBtn.disabled = false;
            if (submitBtnText) submitBtnText.classList.remove('d-none');
            if (submitBtnSpinner) submitBtnSpinner.classList.add('d-none');
          }
          if (closeBtn) closeBtn.disabled = false;
          if (deltaInput) deltaInput.disabled = false;
        }
      });
    }
    
    // Reset form when modal is hidden
    modalEl.addEventListener('hidden.bs.modal', function() {
      if (form) form.reset();
      if (deltaInput) deltaInput.value = '';
      if (submitBtn) {
        submitBtn.disabled = false;
        if (submitBtnText) submitBtnText.classList.remove('d-none');
        if (submitBtnSpinner) submitBtnSpinner.classList.add('d-none');
      }
      if (closeBtn) closeBtn.disabled = false;
      if (deltaInput) deltaInput.disabled = false;
    });
  }
})();
</script>
@endpush
@endsection
