<?php

namespace App\Enums;

enum Callback: string
{
    case VPN_TAG = 'vpn:tag';
    case VPN_CONNECT_TAG = 'vpn:connect';
    case VPN_GUIDE = 'vpn:guide';
    case VPN_BACK = 'vpn:back';
    case VPN_PRICING = 'vpn:pricing';
    case PAYMENT_PRICING = 'payment:pricing';

    public function with(?string $value = null): string
    {
        return $value
            ? $this->value . ':' . $value
            : $this->value;
    }

    public function withMultiple(string ...$values): string
    {
        return $this->value . ':' . implode(':', $values);
    }
}
