<?php

namespace App\Models\Resv;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequester extends Model
{
    use HasFactory;

    protected $connection = 'laravelOdbc';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
    protected $table = 'U_WTM1';
    protected $primaryKey = 'U_DocEntry';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function stages(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Stages::class, "U_WstCode", "U_WstCode");
    }
}
