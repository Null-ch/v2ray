@foreach($users as $user)
  <tr>
    <td class="user-id">{{ $user->id }}</td>
    <td class="user-name">@{{ $user->tg_tag ?? 'N/A' }}</td>
    <td>
      <span class="badge bg-green">Активен</span>
    </td>
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
                data-username="{{ $user->tg_tag ?? 'N/A' }}"
                data-balance="{{ number_format($user->balance->balance ?? 0, 2) }}"
                data-sign="+"
                title="Начислить баланс">
          Начислить баланс
        </button>
        <button type="button"
                class="btn btn-danger btn-sm btn-glass btn-user-balance-sign"
                data-uid="{{ $user->id }}"
                data-username="{{ $user->tg_tag ?? 'N/A' }}"
                data-balance="{{ number_format($user->balance->balance ?? 0, 2) }}"
                data-sign="-"
                title="Списать баланс">
          Списать баланс
        </button>
        <a href="{{ route('cockpit.user.show', $user->id) }}" class="btn btn-info btn-sm btn-glass" title="Просмотр">
          <i class="ti ti-eye"></i>
        </a>
      </div>
    </td>
  </tr>
@endforeach

