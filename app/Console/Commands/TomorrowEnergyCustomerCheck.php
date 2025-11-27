<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\CustomerList;
use App\Models\ProviderIntegration;
use App\Models\UtilitySupportedFuel;
use App\Models\Utility;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Sftp\SftpAdapter;
use Illuminate\Console\Command;

class TomorrowEnergyCustomerCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tomorrow:customerfiles {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download and process Tomorrow Energy customer files.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function dxcToFocusUtilityMap($util)
    {
        switch ($util) {
            case 'ACE':
                return 'Atlantic City Electricity';
            case 'AEPCPL':
                return 'AEP Central';
            case 'AEPWTU':
                return 'AEP North';
            case 'ALE':
                return 'Potomac Edison';
            case 'BGE':
                return 'Baltimore Gas & Electric';
            case 'CIOH':
                return 'The Illuminating Company';
            case 'COLGASPA':
                return 'Columbia Gas PA';
            case 'ComEd':
                return 'ComEd';
            case 'CONED':
                return 'ConEd';
            case 'CNP':
                return 'Centerpoint';
            case 'CPG':
                return 'UGI Central';
            case 'CSP':
                return 'AEP Columbus Southern';
            case 'DPL':
                return 'Dayton Power and Light Company';
            case 'DPLMD':
                return 'Delmarva Power MD';
            case 'DQE':
                return 'Duquesne Light';
            case 'DUKE':
                return 'DUKE';
            case 'JCPL':
                return 'Jersey Central Power & Light';
            case 'MetEd':
                return 'MetEd';
            case 'NJNG':
                return 'New Jersey Natural Gas';
            case 'OED':
                return 'Ohio Edison';
            case 'ONC':
                return 'Oncor';
            case 'OPC':
                return 'AEP Ohio';
            case 'OR':
                return 'Orange and Rockland';
            case 'PECO':
                return 'PECO';
            case 'PENNP':
                return 'Penn Power';
            case 'PEPMD':
                return 'Pepco MD';
            case 'PNELEC':
                return 'Penelec';
            case 'PNG':
                return 'Peoples Gas - PNG';
            case 'PNGED':
                return 'Peoples Equitable Division';
            case 'PPL':
                return 'PPL';
            case 'PSEG':
                return 'Public Service Electric and Gas';
            case 'PTWP':
                return 'Peoples Gas - PTWP';
            case 'SJG':
                return 'South Jersey Gas';
            case 'SMECO':
                return 'Southern Maryland Electric Cooperative';
            case 'TED':
                return 'Toledo Edison';
            case 'TNMP':
                return 'Texas New Mexico Power';
            case 'UGI':
                return 'UGI Utilities';
            case 'UPNG':
                return 'UGI North';
            case 'WPP':
                return 'West Penn Power';
            default:
                return null;
        }
    }

    /**
     * File FTP Download.
     *
     * @param array  $config - configuration needed to perform FTP upload
     * @param string $type   - file type
     *
     * @return array
     */
    public function ftpDownload($config, $type, $brand_id)
    {
        info('FTP at '.Carbon::now());
        try {
            $adapter = new SftpAdapter(
                [
                    'host' => $config['hostname'],
                    'username' => $config['username'],
                    'password' => $config['password'],
                    'port' => 22,
                    'root' => (isset($config['root'])) ? $config['root'] : '/',
                    'directoryPerm' => 0755,
                    'timeout' => 10,
                ]
            );

            $filesystem = new Filesystem($adapter);

            $type_id = ($type == 'blacklist')
                ? 1 : 2;
            $root = ($type == 'blacklist')
                ? '/Customer Blacklist'
                : '/ACL';

            info('Root is '.$root);

            $array = [];
            $files = $filesystem->listContents($root);
            foreach ($files as $file) {
                $array[$file['timestamp']][] = $file;
            }

            ksort($array);

            $current = end($array);

            info('Get file '.$current[0]['path']);
            $contents = $filesystem->read($current[0]['path']);
            $lines = explode(PHP_EOL, $contents);
            $csv = [];
            foreach ($lines as $line) {
                if (strlen(trim($line)) > 0) {
                    $csv[] = str_getcsv($line);
                }
            }

            // Remove header
            unset($csv[0]);

            $no_utility = [];
            $no_commodity_assigned = [];

            CustomerList::where(
                'brand_id',
                $brand_id
            )->update(
                [
                    'processed' => 0,
                ]
            );

            foreach ($csv as $c) {
                switch ($c[1]) {
                    case 'Electric':
                        $commodity = 1;
                        break;
                    case 'Natural Gas':
                        $commodity = 2;
                        break;
                }

                // If c[3] is the string NULL, set it to actual null.
                if (isset($c[3]) && $c[3] === 'NULL') {
                    $c[3] = null;
                }

                $util = $this->dxcToFocusUtilityMap($c[0]);
                if ($util) {
                    $utility = Cache::remember(
                        'utility_'.md5($util),
                        1800,
                        function () use ($util) {
                            return Utility::where(
                                'name',
                                $util
                            )->first();
                        }
                    );
                    if ($utility) {
                        $usf = Cache::remember(
                            'usf_'.md5($utility->id.'-'.$commodity),
                            1800,
                            function () use ($utility, $commodity) {
                                return UtilitySupportedFuel::where(
                                    'utility_id',
                                    $utility->id
                                )->where(
                                    'utility_fuel_type_id',
                                    $commodity
                                )->first();
                            }
                        );
                        if ($usf) {
                            $cl = CustomerList::where(
                                'brand_id',
                                $brand_id
                            )->where(
                                'customer_list_type_id',
                                $type_id
                            )->where(
                                'utility_supported_fuel_id',
                                $usf->id
                            )->where(
                                'account_number1',
                                $c[2]
                            );

                            if (isset($c[3])) {
                                $cl = $cl->where(
                                    'account_number2',
                                    $c[3]
                                );
                            }

                            $cl = $cl->withTrashed()->first();
                            if ($cl && $cl->trashed()) {
                                $cl->restore();
                            }

                            if (!$cl) {
                                $cl = new CustomerList();
                                $cl->customer_list_type_id = $type_id;
                                $cl->brand_id = $brand_id;
                                $cl->utility_supported_fuel_id = $usf->id;
                                $cl->account_number1 = $c[2];

                                if (isset($c[3])) {
                                    $cl->account_number2 = $c[3];
                                }

                                $cl->filename = $current[0]['filename'];
                            }

                            $cl->processed = 1;
                            $cl->save();
                        } else {
                            $no_commodity_assigned[] = $util;
                        }
                    }
                } else {
                    $no_utility[] = $c[0];
                }
            }

            CustomerList::where(
                'brand_id',
                $brand_id
            )->where(
                'processed',
                0
            )->delete();

            info(print_r($no_utility, true));
            info(print_r($no_commodity_assigned, true));

            // return [
            //     'import_file_name' => $current[0]['filename'],
            // ];
        } catch (\Exception $e) {
            info('Error! The reason reported is: '.$e);

            if ($this->option('debug')) {
                echo 'Error: '.$e."\n";
            }
        }

        return null;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $debug = $this->option('debug');
        $brand = Brand::where(
            'name',
            'Tomorrow Energy'
        )->first();
        if ($debug) {
            print_r($brand->toArray());
        }

        if ($brand) {
            $pi = ProviderIntegration::where(
                'service_type_id',
                7
            )->where(
                'brand_id',
                $brand->id
            )->first();
            if ($pi) {
                $config = [
                    'hostname' => $pi->hostname,
                    'username' => $pi->username,
                    'password' => $pi->password,
                ];

                if ($debug) {
                    print_r($config);
                }

                // Active Customer List
                $this->ftpDownload($config, 'active customer', $brand->id);

                // Customer Blacklist Check
                // $this->ftpDownload($config, 'blacklist', $brand->id);
            }
        }
    }
}
