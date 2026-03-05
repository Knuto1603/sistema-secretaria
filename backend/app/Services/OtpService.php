<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Exception;

class OtpService
{
    /**
     * Minutos de validez del OTP
     */
    protected int $expirationMinutes = 10;

    /**
     * Genera un nuevo código OTP para el usuario
     */
    public function generate(User $user, string $purpose = 'login'): OtpCode
    {
        // Invalidar OTPs anteriores del mismo propósito
        $this->invalidatePrevious($user, $purpose);

        // Generar código de 6 dígitos
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Crear el OTP
        return OtpCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes($this->expirationMinutes),
        ]);
    }

    /**
     * Verifica si un código OTP es válido
     */
    public function verify(User $user, string $code, string $purpose = 'login'): bool
    {
        $otp = OtpCode::where('user_id', $user->id)
            ->where('code', $code)
            ->where('purpose', $purpose)
            ->valid()
            ->first();

        if (!$otp) {
            return false;
        }

        // Marcar como verificado
        $otp->markAsVerified();

        return true;
    }

    /**
     * Obtiene el OTP válido actual del usuario (si existe)
     */
    public function getValidOtp(User $user, string $purpose = 'login'): ?OtpCode
    {
        return OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->valid()
            ->first();
    }

    /**
     * Invalida todos los OTPs anteriores del usuario para un propósito específico
     */
    public function invalidatePrevious(User $user, string $purpose = 'login'): void
    {
        OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);
    }

    /**
     * Envía el OTP por correo electrónico
     */
    public function send(User $user, string $purpose = 'login'): OtpCode
    {
        // Generar nuevo OTP
        $otp = $this->generate($user, $purpose);

        // Obtener email del usuario (institucional para estudiantes)
        $email = $user->getEmailInstitucional();

        // Enviar correo
        try {
            Mail::to($email)->send(new OtpMail($user, $otp->code, $this->expirationMinutes));
        } catch (Exception $e) {
            // Log del error pero no fallar (el OTP ya se generó)
            \Log::error('Error enviando OTP: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'email' => $email,
            ]);
        }

        return $otp;
    }

    /**
     * Limpia OTPs expirados (para ejecutar en un comando scheduled)
     */
    public function cleanExpired(): int
    {
        return OtpCode::where('expires_at', '<', now())
            ->whereNull('verified_at')
            ->delete();
    }

    /**
     * Verifica si el usuario puede solicitar un nuevo OTP
     * (rate limiting básico: 1 OTP cada 60 segundos)
     */
    public function canRequestNew(User $user, string $purpose = 'login'): bool
    {
        $lastOtp = OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if (!$lastOtp) {
            return true;
        }

        // Permitir nuevo OTP después de 60 segundos
        return $lastOtp->created_at->addSeconds(60)->isPast();
    }

    /**
     * Obtiene los segundos restantes para poder solicitar nuevo OTP
     */
    public function secondsUntilCanRequest(User $user, string $purpose = 'login'): int
    {
        $lastOtp = OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if (!$lastOtp) {
            return 0;
        }

        $canRequestAt = $lastOtp->created_at->addSeconds(60);

        if ($canRequestAt->isPast()) {
            return 0;
        }

        return now()->diffInSeconds($canRequestAt);
    }
}
