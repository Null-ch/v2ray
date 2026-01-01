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

    public function __construct(
        private readonly TelegramService $telegramService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Telegram bot...');

        try {
            $this->telegramService->run();
        } catch (\Throwable $e) {
            $this->error('Error starting Telegram bot: ' . $e->getMessage());
            
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

