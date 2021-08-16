<?php

namespace App\Models\Resv;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReqItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $primaryKey = 'U_DocEntry';
    public $timestamps = false;
    protected $connection = 'laravelOdbc';
    protected $guarded = [];
    protected $table = 'U_OITM';
}
