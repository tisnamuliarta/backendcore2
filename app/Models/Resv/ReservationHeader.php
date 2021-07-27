<?php

namespace App\Models\Resv;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationHeader extends Model
{
    use HasFactory;

    protected $connection = 'laravelOdbc';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
    //    public $incrementing = false;
    protected $table = 'RESV_H';
    protected $primaryKey = 'U_DocEntry';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function details()
    {
        return $this->hasMany(ReservationDetails::class, 'U_DocEntry', 'U_DocEntry');
    }
}
