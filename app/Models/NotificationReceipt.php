<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationReceipt extends Model
{
    public const PROVIDER_ZOHO_CPAAS = 'zoho_cpaas';
    public const PROVIDER_ZEPTOMAIL = 'zeptomail';
    public const PROVIDER_WEBPUSH = 'webpush';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_READ = 'read';

    protected $fillable = [
        'notification_id',
        'channel',
        'provider',
        'provider_message_id',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
