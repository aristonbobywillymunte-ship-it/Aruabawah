<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandingSetting extends Model
{
    protected $table = 'branding_settings';

    protected $fillable = [
        'app_name',
        'app_logo_path',
    ];
}
