<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViewEmployeeLeave extends Model
{
    protected $connection = 'sqlsrv2';
    protected $table = 'vw_employee_leaves';
}
