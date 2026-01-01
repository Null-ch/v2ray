<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

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
        // Resolve TelegramService lazily to avoid issues during command discovery
        if (!app()->bound(TelegramService::class)) {
            $this->error('Telegram bot token is not configured');
            
            return Command::FAILURE;
        }

        $this->info('Starting Telegram bot...');

        try {
            $telegramService = app()->make(TelegramService::class);
            $telegramService->run();
        } catch (\Throwable $e) {
            $this->error('Error starting Telegram bot: ' . $e->getMessage());
            
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

