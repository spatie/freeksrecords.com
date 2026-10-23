<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Auth\EmailVerificationRequest as BaseEmailVerificationRequest;

class EmailVerificationRequest extends BaseEmailVerificationRequest
{
    public function authorize(): bool
    {
        return hash_equals((string) $this->user()->public_id, (string) $this->route('id'))
            && hash_equals(sha1($this->user()->getEmailForVerification()), (string) $this->route('hash'));
    }
}
