<?php

namespace KevinRider\LaravelEtrade\Commands;

use Illuminate\Console\Command;

class LaravelEtradeDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel-etrade:demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a basic demo showcasing Laravel E*TRADE integration.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Laravel E*TRADE demo command is ready.');

        return self::SUCCESS;
    }
}
