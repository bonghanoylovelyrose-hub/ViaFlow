<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class LoanAgreement extends Model
{
    public const PENDING = 'pending';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = ['pdf_base64', 'issued_at'];

    protected $hidden = ['pdf_base64'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'email_sent_at' => 'datetime'];
    }

    public function filename(): string
    {
        return 'loan-agreement-'.$this->loan_id.'.pdf';
    }

    public function pdfBytes(): string
    {
        $bytes = base64_decode($this->pdf_base64, true);
        if ($bytes === false || ! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('The saved loan agreement PDF is invalid.');
        }

        return $bytes;
    }
}
