<?php

namespace App\Models\Resv;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLeave extends Model
{
    use HasFactory;

    protected $connection = 'laravelOdbc';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
    protected $table = 'OUSR_LEAVE';
    protected $primaryKey = 'U_DocEntry';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
