<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferLivestock extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_document_id',
        'tag_no',
        'category',
        'colour',
        'weight',
        'condition_good',
        'condition_not_good',
        'remarks',
        'purpose',
        'yard',
        'unit_cost',
        'value',
        'sort_order',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'value' => 'decimal:2',
        'condition_good' => 'boolean',
        'condition_not_good' => 'boolean',
    ];

    public function document()
    {
        return $this->belongsTo(TransferDocument::class, 'transfer_document_id');
    }

    public function cattle()
    {
        return $this->belongsTo(Cattle::class, 'tag_no', 'tag_no');
    }
}
