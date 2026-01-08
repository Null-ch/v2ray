@foreach($users as $user)
  <tr>
    <td class="user-id">{{ $user->id }}</td>
    <td class="user-name">{{ $user->name ?? $user->tg_tag ?? 'N/A' }}</td>
    <td>{{ $user->tg_id ?? 'N/A' }}</td>
    <td>
      {{ $user->subscriptions->count() }} шт.
    </td>
    <td>{{ number_format($user->balance->balance ?? 0, 2) }}</td>
    <td>
      <div class="btn-list">
        <a class="btn btn-primary btn-sm btn-glass" href="{{ route('cockpit.key.index', ['user_id' => $user->id]) }}" title="Выдать ключ">
          <i class="ti ti-key"></i>
        </a>
        <a href="{{ route('cockpit.user.show', $user->id) }}" class="btn btn-info btn-sm btn-glass" title="Просмотр">
          <i class="ti ti-eye"></i>
        </a>
        <a href="{{ route('cockpit.user.edit', $user->id) }}" class="btn btn-warning btn-sm btn-glass" title="Редактировать">
          <i class="ti ti-edit"></i>
        </a>
      </div>
    </td>
  </tr>
@endforeach

