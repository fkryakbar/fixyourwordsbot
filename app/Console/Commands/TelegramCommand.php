<?php

namespace App\Console\Commands;

use App\Services\Telegram;
use Illuminate\Console\Command;

class TelegramCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reply Unread Message from Telegram API';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $this->info('Telegram API is running in the background...');
        while (true) {
            Telegram::replyUnredMessage();
            sleep(1);
        }
    }
}
