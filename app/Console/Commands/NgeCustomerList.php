<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\ProviderIntegration;
use Illuminate\Support\Facades\Artisan;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;
use Illuminate\Console\Command;

class NgeCustomerList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nge:customerlist {--brand=} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'NGE Customer List Loading (long process)';

    /**
     * Create a new command instance.
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
        $brand = Brand::find($this->option('brand'));
        if ($this->option('debug')) {
            info(print_r($brand->toArray(), true));
        }

        if ($brand) {
            $pi = ProviderIntegration::where(
                'brand_id',
                $brand->id
            )->first();
            if ($pi) {
                if (is_string($pi->notes)) {
                    $pi->notes = json_decode($pi->notes, true);
                }

                if ($this->option('debug')) {
                    info(print_r($pi->toArray(), true));
                }

                $config = [
                    'hostname' => $pi->hostname,
                    'username' => $pi->username,
                    'password' => $pi->password,
                    'root' => '/',
                    'port' => 22,
                    'timeout' => 10,
                    'directoryPerm' => 0755,
                ];

                if ($this->option('debug')) {
                    info(print_r($config, true));
                }

                $adapter = new SftpAdapter(
                    [
                        'host' => $config['hostname'],
                        'port' => 22,
                        'username' => $config['username'],
                        'password' => $config['password'],
                        'root' => '/Approved BTN Lists',
                        'timeout' => 10,
                        'directoryPerm' => 0755,
                    ]
                );

                $filesystem = new Filesystem($adapter);
                $contents = $filesystem->read('Master-Leads.txt');
                $file = public_path('tmp').'/'.time().'.txt';

                file_put_contents($file, $contents);

                Artisan::call('customerlist:import', [
                    '--file' => $file,
                    '--replace' => true,
                    '--brand' => $this->option('brand'),
                    '--type' => 3,
                ]);

                unlink($file);
            }
        }
    }
}
