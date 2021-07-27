<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionApprovalDetails extends Model
{
    use HasFactory;

    protected $connection = 'laravelOdbc';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
    protected $table = 'U_WDD1';
    protected $primaryKey = 'U_DocEntry';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
