<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ClientPackagePermission extends Pivot
{
    protected $table = 'client_package_permissions';

    protected $fillable = [
        'user_id',
        'package_id',
    ];
}
