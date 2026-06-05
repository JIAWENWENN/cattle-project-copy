<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'user',
        'action',
        'detail',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
