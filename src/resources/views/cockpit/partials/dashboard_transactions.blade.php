@forelse($transactions as $transaction)
  <tr>
    <td>
      @if($transaction->user)
        <a href="{{ route('cockpit.user.show', $transaction->user->id) }}">
          {{ $transaction->user->name }} (#{{ $transaction->user->id }})
        </a>
      @else
        <span class="text-muted">-</span>
      @endif
    </td>
    <td>
      @if($transaction->metadata && isset(json_decode($transaction->metadata, true)['xui_tag']))
        {{ json_decode($transaction->metadata, true)['xui_tag'] }}
      @else
        <span class="text-muted">-</span>
      @endif
    </td>
    <td>
      @if($transaction->metadata && isset(json_decode($transaction->metadata, true)['pricing_title']))
        {{ json_decode($transaction->metadata, true)['pricing_title'] }}
      @else
        <span class="text-muted">-</span>
      @endif
    </td>
    <td>{{ number_format($transaction->amount, 2) }} RUB</td>
    <td>{{ $transaction->created_at->format('d.m.Y H:i') }}</td>
  </tr>
@empty
  <tr>
    <td colspan="5" class="text-center">Нет транзакций</td>
  </tr>
@endforelse

