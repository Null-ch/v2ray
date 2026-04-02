<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TrustProxies
{
    /**
     * Список доверенных прокси.
     * '*' = доверяем всем
     *
     * @var array|string|null
     */
    protected $proxies = ['*'];

    /**
     * Заголовки, используемые для определения протокола и IP.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->proxies !== null) {
            Request::setTrustedProxies(
                $this->proxies ?? [],
                $this->headers
            );
        }

        return $next($request);
    }
}
