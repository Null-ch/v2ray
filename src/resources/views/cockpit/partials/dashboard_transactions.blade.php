@forelse($transactions as $transaction)
  @php
    $meta = is_array($transaction->metadata)
      ? $transaction->metadata
      : (json_decode((string) $transaction->metadata, true) ?? []);
    $tag = $meta['xui_tag'] ?? $meta['vpn_tag'] ?? null;
    $pricingLabel = $meta['pricing_title']
      ?? (isset($meta['pricing_id']) ? 'Тариф #' . $meta['pricing_id'] : null);
  @endphp
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
      @if($tag)
        {{ $tag }}
      @else
        <span class="text-muted">-</span>
      @endif
    </td>
    <td>
      @if($pricingLabel)
        {{ $pricingLabel }}
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

