<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
        ]);

        $site = Site::query()->whereKey($data['site_id'])->where('status', 'active')->firstOrFail();
        $request->session()->put('current_site_id', $site->id);

        return back();
    }
}
