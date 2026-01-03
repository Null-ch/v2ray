<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
    protected $signature = 'telegram:bot {--force : Force start even if another instance is running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Telegram bot (polling mode only)';

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

        // Проверяем наличие других запущенных процессов бота
        if (!$this->option('force') && $this->isAnotherInstanceRunning()) {
            $this->error('Another Telegram bot instance is already running!');
            $this->warn('To find and stop it, run:');
            $this->line('  ps aux | grep "telegram:bot"');
            $this->line('  kill <PID>');
            $this->warn('Or use --force flag to ignore this check (not recommended)');
            return Command::FAILURE;
        }

        // Проверяем статус вебхука перед запуском (если метод доступен)
        $this->info('Checking webhook status...');
        try {
            /** @var TelegramService $telegramService */
            $telegramService = app(TelegramService::class);
            $bot = $telegramService->getBot();
            
            // Пытаемся проверить статус вебхука (метод может отсутствовать в некоторых версиях)
            if (method_exists($bot, 'getWebhookInfo')) {
                $webhookInfo = $bot->getWebhookInfo();
                if (!empty($webhookInfo->url ?? null)) {
                    $this->warn("⚠️  Webhook is still active: {$webhookInfo->url}");
                    $this->info('Webhook will be deleted automatically before starting polling...');
                } else {
                    $this->info('✓ No webhook is set');
                }
            } else {
                $this->info('Webhook status check not available, will attempt to delete webhook before starting');
            }
        } catch (\Throwable $e) {
            $this->warn('Could not check webhook status: ' . $e->getMessage());
            $this->info('Will attempt to delete webhook before starting polling...');
        }

        $this->info('Starting Telegram bot in polling mode...');

        try {
            /** @var TelegramService $telegramService */
            $telegramService = app(TelegramService::class);

            // Запускаем бота (обработчики регистрируются внутри TelegramService::run())
            $telegramService->run();
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            
            // Более понятное сообщение для конфликта
            if (str_contains($errorMessage, 'Conflict') || str_contains($errorMessage, 'getUpdates')) {
                $this->error('❌ Conflict: Another bot instance is using getUpdates!');
                $this->newLine();
                $this->warn('This usually means:');
                $this->line('  1. Another process is running: php artisan telegram:bot');
                $this->line('  2. A webhook is still active in Telegram');
                $this->newLine();
                $this->info('Solutions:');
                $this->line('  • Check for other processes: ps aux | grep "telegram:bot"');
                $this->line('  • Kill other process: kill <PID>');
                $this->line('  • Delete webhook manually:');
                $token = config('services.telegram.bot_token');
                if ($token) {
                    $this->line('    curl -X POST "https://api.telegram.org/bot' . $token . '/deleteWebhook"');
                }
            } else {
                $this->error('Error starting Telegram bot: ' . $errorMessage);
            }
            
            Log::error('Error starting Telegram bot', [
                'message' => $errorMessage,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Check if another bot instance is running
     */
    private function isAnotherInstanceRunning(): bool
    {
        // Проверяем наличие других процессов с командой telegram:bot
        // Исключаем текущий процесс (если он уже запущен)
        $command = 'ps aux | grep "[p]hp.*artisan.*telegram:bot" | grep -v grep';
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Для Windows используем tasklist
            $command = 'tasklist /FI "IMAGENAME eq php.exe" /FO CSV | findstr "artisan"';
            // На Windows сложнее проверить, поэтому возвращаем false
            // Пользователь должен сам следить за этим
            return false;
        }
        
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        // Если найдены процессы (кроме текущего), значит другой экземпляр запущен
        $processCount = count(array_filter($output, function($line) {
            // Исключаем строки, которые не содержат telegram:bot
            return str_contains($line, 'telegram:bot');
        }));
        
        // Если процессов больше 0, значит есть другой запущенный экземпляр
        // (текущий процесс еще не появился в списке или мы его не учитываем)
        return $processCount > 0;
    }
}
