<?php

namespace App\Enums;

enum NotificationDeliveryStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sending = 'sending';
    case Retrying = 'retrying';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
