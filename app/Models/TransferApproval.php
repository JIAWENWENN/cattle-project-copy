<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_document_id',
        'approver_id',
        'step',
        'action',
        'comments',
    ];

    protected $casts = [
        'action' => 'boolean',
    ];

    public function document()
    {
        return $this->belongsTo(TransferDocument::class, 'transfer_document_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
