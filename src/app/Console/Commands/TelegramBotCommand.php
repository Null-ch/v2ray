<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramBotHandlers;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class TelegramBotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:bot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Telegram bot';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Проверяем, что сервис зарегистрирован
        if (!app()->bound(TelegramService::class)) {
            $this->error('Telegram bot token is not configured');
            return Command::FAILURE;
        }

        $this->info('Starting Telegram bot...');

        try {
            /** @var TelegramService $telegramService */
            $telegramService = app(TelegramService::class);

            // Регистрируем обработчики прямо перед запуском бота
            /** @var TelegramBotHandlers $handlers */
            $handlers = app(TelegramBotHandlers::class);
            $handlers->registerHandlers();

            Log::info('All Telegram bot handlers registered, starting bot long polling');

            // Запускаем бота
            $telegramService->run();
        } catch (\Throwable $e) {
            $this->error('Error starting Telegram bot: ' . $e->getMessage());
            Log::error('Error starting Telegram bot', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
