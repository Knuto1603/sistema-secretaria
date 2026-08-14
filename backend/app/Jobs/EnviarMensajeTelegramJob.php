<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnviarMensajeTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $userId,
        private readonly string $texto,
    ) {}

    public function handle(TelegramBotService $bot): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            return;
        }

        $bot->notificar($user, $this->texto);
    }
}
