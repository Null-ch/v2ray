<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

final class SuccessResource extends JsonResource
{
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_OK);
    }

    public static $wrap = null;

    public function toArray($request): array
    {
        return array_merge($this->resource, [
            'status' => 'success',
            'code' => Response::HTTP_OK,
            'meta' => [
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }
}
