<?php

namespace App\Enums;

enum Callback: string
{
    case VPN_TAG = 'vpn:tag';
    case VPN_CONNECT_TAG = 'vpn:connect:tag';
    case VPN_GUIDE = 'vpn:guide';
    case VPN_BACK = 'vpn:back';
    case VPN_PRICING = 'vpn:pricing:tag';

    public function with(?string $value = null): string
    {
        return $value
            ? $this->value . ':' . $value
            : $this->value;
    }
}
