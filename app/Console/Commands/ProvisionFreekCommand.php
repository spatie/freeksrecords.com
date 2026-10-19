<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

class ProvisionFreekCommand extends Command
{
    protected $signature = 'social:provision-freek';

    protected $description = 'Send a password setup link only to Freek’s reserved inbox';

    public function handle(): int
    {
        if (User::freek()->email !== 'freek@spatie.be') {
            $this->error('The reserved owner email does not match. No link sent.');

            return self::FAILURE;
        }
        $status = Password::sendResetLink(['email' => 'freek@spatie.be']);
        $this->info(__($status));

        return $status === Password::ResetLinkSent ? self::SUCCESS : self::FAILURE;
    }
}
