<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\BrandUser;
use App\Models\Rate;
use App\Models\Vendor;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

class TLPDataPush extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tlp:data:push';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push data to TLP via FTP because they suck.';

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
        // This is currently only for Tomorrow Energy.
        // Would need to be modified to support others.

        $adapter = new Ftp(
            [
                'host' => 'ftp.dxc-inc.com',
                'username' => 'tlp_sperian',
                'password' => 'how it feels to chew 5 gum',
                'port' => (isset($config['port'])) ? $config['port'] : 21,
                'root' => (isset($config['root'])) ? $config['root'] : '/',
                'passive' => true,
                'timeout' => 30,
            ]
        );

        $filesystem = new Filesystem($adapter);

        $brands = [
            'Tomorrow Energy',
        ];

        foreach ($brands as $brand) {
            $b = Brand::where(
                'name',
                $brand
            )->first();
            if ($b) {
                $rate_file = public_path('tmp/Tomorrow_Energy_RATE_LIST.csv');
                $fp = fopen($rate_file, 'w');
                $rates = Rate::select(
                    'states.state_abbrev AS sales_state',
                    'rates.program_code',
                    'products.market',
                    'products.name AS product_name',
                    'utilities.name AS utility_name',
                    'utility_types.utility_type AS fuel_type',
                    'rate_types.rate_type',
                    'products.service_fee',
                    'products.term',
                    'rates.cancellation_fee',
                    'rates.rate_amount',
                    'rates.external_rate_id AS rate_id',
                    'rates.dual_only AS duel_fuel_only'
                )->leftJoin(
                    'products',
                    'rates.product_id',
                    'products.id'
                )->leftJoin(
                    'utility_supported_fuels',
                    'rates.utility_id',
                    'utility_supported_fuels.id'
                )->leftJoin(
                    'utilities',
                    'utility_supported_fuels.utility_id',
                    'utilities.id'
                )->leftJoin(
                    'states',
                    'utilities.state_id',
                    'states.id'
                )->leftJoin(
                    'utility_types',
                    'utility_supported_fuels.utility_fuel_type_id',
                    'utility_types.id'
                )->leftJoin(
                    'rate_types',
                    'products.rate_type_id',
                    'rate_types.id'
                )->where(
                    'rates.tlp',
                    1
                )->where(
                    'products.brand_id',
                    $b->id
                )->get()->toArray();

                $i = 0;
                foreach ($rates as $fields) {
                    if ($i == 0) {
                        fputcsv($fp, array_keys($fields));
                    }

                    fputcsv($fp, array_values($fields));

                    $i++;
                }

                fclose($fp);

                try {
                    $stream = fopen($rate_file, 'r+');
                    $filesystem->writeStream(
                        'Tommorrow_Energy_RATE_LIST.csv',
                        $stream
                    );

                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                } catch (\Exception $e) {
                    echo 'Error! The reason reported is: '.$e;
                }

                $sales_agent_file = public_path('tmp/Tomorrow_Energy_AGENT_LIST.csv');
                $fp = fopen($sales_agent_file, 'w');
                $sales_agents = BrandUser::select(
                    'brand_users.works_for_id',
                    'brand_users.employee_of_id',
                    DB::raw('NULL AS vendor_id'),
                    'brand_users.tsr_id',
                    'users.first_name AS tsr_fname',
                    'users.last_name AS tsr_lname',
                    DB::raw('NULL AS tsr_language'),
                    DB::raw('NULL AS phone'),
                    'brand_users.created_at AS dt_date',
                    'brand_users.created_at AS dt_added',
                    DB::raw('(CASE WHEN brand_users.status = 1 THEN "T" ELSE "F" END) AS active'),
                    'brand_users.tsr_id AS rec_id'
                )->leftJoin(
                    'users',
                    'brand_users.user_id',
                    'users.id'
                )->where(
                    'brand_users.works_for_id',
                    $b->id
                )->where(
                    'brand_users.status',
                    1
                )->where(
                    'brand_users.role_id',
                    3
                )->get()->map(
                    function ($item) {
                        $vendor = Vendor::where(
                            'vendor_id',
                            $item->employee_of_id
                        )->where(
                            'brand_id',
                            $item->works_for_id
                        )->first();
                        if ($vendor) {
                            $item->vendor_id = $vendor->vendor_label;
                        }

                        unset($item->works_for_id);
                        unset($item->employee_of_id);

                        return $item;
                    }
                )->toArray();

                $i = 0;
                foreach ($sales_agents as $fields) {
                    if ($i == 0) {
                        fputcsv($fp, array_keys($fields));
                    }

                    fputcsv($fp, array_values($fields));

                    $i++;
                }

                fclose($fp);

                try {
                    $stream = fopen($sales_agent_file, 'r+');
                    $filesystem->writeStream(
                        'Tomorrow_Energy_AGENT_LIST.csv',
                        $stream
                    );

                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                } catch (\Exception $e) {
                    echo 'Error! The reason reported is: '.$e;
                }

                @unlink($sales_agent_file);
                @unlink($rate_file);
            }
        }
    }
}
