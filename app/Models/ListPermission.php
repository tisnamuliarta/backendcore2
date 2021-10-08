<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListPermission extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'list_permissions';
}
