<?php

namespace App\Helpers;

use App\Models\BrandingSetting;
use Illuminate\Support\Facades\Cache;

class AppBrandingHelper
{
    public static function getAppName(): string
    {
        return Cache::remember('app_branding_name', 3600, function () {
            return BrandingSetting::first()?->app_name ?? 'ARUSBAWAH';
        });
    }

    public static function getAppLogoPath(): ?string
    {
        return Cache::remember('app_branding_logo_path', 3600, function () {
            return BrandingSetting::first()?->app_logo_path;
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('app_branding_name');
        Cache::forget('app_branding_logo_path');
    }
}
