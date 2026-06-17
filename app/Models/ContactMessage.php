<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'site_id',
        'type',
        'locale',
        'subject',
        'name',
        'email',
        'phone',
        'address',
        'content',
        'is_read',
        'status',
        'admin_note',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'handled_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
