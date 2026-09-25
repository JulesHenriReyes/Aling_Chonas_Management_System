<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PaymentSetting extends Model
{
    protected $fillable = ['account_name', 'account_number', 'qr_path', 'updated_by'];

    public function isConfigured(): bool
    {
        return filled($this->account_name) && filled($this->account_number)
            && filled($this->qr_path) && Storage::disk('public')->exists($this->qr_path);
    }
}
