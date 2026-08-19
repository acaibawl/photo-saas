<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'event_type', 'stripe_account_id', 'event_created'])]
class StripeWebhookEvent extends Model
{
    use HasUlids;
}
