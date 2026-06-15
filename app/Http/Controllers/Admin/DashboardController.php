<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Product;
use App\Support\AdminContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(AdminContext $context): View
    {
        $site = $context->site();

        return view('admin.dashboard', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'contentCount' => $site ? Content::query()->where('site_id', $site->id)->count() : 0,
            'contentTypeCount' => $site ? ContentType::query()->where('site_id', $site->id)->count() : 0,
            'productCount' => class_exists(Product::class) && $site ? Product::query()->where('site_id', $site->id)->count() : 0,
        ]);
    }
}
