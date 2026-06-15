<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminContext
{
    public function __construct(private readonly Request $request)
    {
    }

    public function user(): ?AdminUser
    {
        $adminId = $this->request->session()->get('admin_user_id');

        return $adminId ? AdminUser::query()->find($adminId) : null;
    }

    public function sites(): Collection
    {
        return Site::query()
            ->with('tenant')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function site(): ?Site
    {
        $siteId = $this->request->session()->get('current_site_id');
        $site = $siteId ? Site::query()->whereKey($siteId)->first() : null;

        if ($site) {
            return $site;
        }

        $site = $this->sites()->first();

        if ($site) {
            $this->request->session()->put('current_site_id', $site->id);
        }

        return $site;
    }
}
