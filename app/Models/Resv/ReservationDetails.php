<?php

namespace App\Models\Resv;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationDetails extends Model
{
    use HasFactory;

    protected $connection = 'laravelOdbc';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public $incrementing = false;
    protected $table = 'RESV_D';
    protected $primaryKey = 'LineEntry';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function header()
    {
        return $this->belongsTo(ReservationHeader::class, 'U_DocEntry', 'U_DocEntry');
    }
}
