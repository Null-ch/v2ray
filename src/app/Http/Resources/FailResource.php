<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

final class FailResource extends JsonResource
{
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
    }

    public static $wrap = null;

    public function toArray($request): array
    {
        return array_merge($this->resource, [
            'status' => 'error',
            'code' => Response::HTTP_BAD_REQUEST,
            'meta' => [
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }
}
