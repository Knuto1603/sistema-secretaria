<?php

namespace App\Console\Commands;

use App\Services\OtpService;
use Illuminate\Console\Command;

class CleanExpiredOtps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina los códigos OTP expirados de la base de datos';

    /**
     * Execute the console command.
     */
    public function handle(OtpService $otpService): int
    {
        $deleted = $otpService->cleanExpired();

        $this->info("Se eliminaron {$deleted} códigos OTP expirados.");

        return Command::SUCCESS;
    }
}
