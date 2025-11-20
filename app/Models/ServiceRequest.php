<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $fillable = [
        'cliente_id', 'tecnico_id', 'service_id', 'client_latitude', 'client_longitude',
        'status', 'requested_at', 'rating', 'comments'
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }
}
