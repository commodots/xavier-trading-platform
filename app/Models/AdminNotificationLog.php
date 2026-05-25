<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotificationLog extends Model
{
    protected $fillable = ['title', 'message', 'recipient_count', 'sent_email', 'sent_message'];
}
