<?php

namespace App\Console\Commands;

use App\Models\TpvStaffPermission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\StreamOutput;

class UpdatePermissionsForRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:update-from-routes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates permissions based on named routes';

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

        $handle = tmpfile();
        $out = new StreamOutput($handle);
        Artisan::call('route:list --columns=uri,name,method --json', [], $out);
        $fname = stream_get_meta_data($handle)['uri'];
        $routes = json_decode(file_get_contents($fname), true);
        fclose($handle);

        $cleanRoutes = array_filter($routes, function ($route) {
            if ($route['name'] == null) {
                return false;
            }
            if (strstr($route['name'], '.') !== false) {
                return true;
            }
            return false;
        });
        $count = 0;
        $this->info('Checking for existing permissions and adding missing items');
        $bar = $this->output->createProgressBar(count($cleanRoutes));
        $bar->start();
        foreach ($cleanRoutes as $route) {
            $perm = TpvStaffPermission::where('short_name', $route['name'])->first();
            if (!$perm) {
                $perm = new TpvStaffPermission();
                $perm->short_name = $route['name'];
                $perm->friendly_name = $route['name'];
                $perm->description = $route['name'] . ' automatically created from route';
                $perm->save();
                $count++;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->line('');
        $this->info('Added ' . $count . ' permissions.');
    }
}
