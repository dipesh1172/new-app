<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Hash;
use Illuminate\Console\Command;

class HashString extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hash:string {--string=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hash a string';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->option('string')) {
            echo 'Syntax: php artisan hash:string --string=<your string>';
            exit();
        }

        echo Hash::make($this->option('string')) . "\n";
    }
}
