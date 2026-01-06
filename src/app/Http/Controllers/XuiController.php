<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\XuiService;
use Illuminate\Http\Request;

final class XuiController
{
    public function __construct(private readonly XuiService $xuiService) {}

    public function getConfigImportLink(Request $request)
    {
        $tag = $request->query('tag');
        $uuid = $request->query('uuid');
        $configUrl = $this->xuiService->getSubLink($tag, $uuid);
        $v2raytunUrl = 'v2raytun://import/' . $configUrl;

        return view('v2raytun-redirect', ['link' => $v2raytunUrl]);
    }
}
