<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        "baidu_name",
        "netdisk_name",
        "cookie",
        "vip_type",
        "switch",
        "reason",
        "prov",
        "svip_end_at",
        "last_use_at"
    ];
}
