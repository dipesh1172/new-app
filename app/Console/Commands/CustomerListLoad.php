<?php

namespace App\Console\Commands;

use Ramsey\Uuid\Uuid;
use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\UtilitySupportedFuel;
use App\Models\ProviderIntegration;
use App\Models\CustomerList;
use App\Models\Brand;

class CustomerListLoad extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:list:load {--debug} {--brand=} {--no-restore}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load Active Customer/Blacklist from FTP server';

    private $utilityCache = [];
    private $doRestore = true;
    private $missingUtilities = [];

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
        if ($this->option('no-restore')) {
            $this->doRestore = false;
        }
        $brand_id = $this->option('brand');
        if (empty($brand_id)) {
            $this->error('No Brand Specified, please use the --brand option.');
            return 42;
        }

        $brand = Brand::find($brand_id);

        if ($brand) {
            $pi = ProviderIntegration::where(
                'brand_id',
                $brand->id
            )->where('provider_integration_type_id', 7)
                ->first();
            if ($pi) {
                if (is_string($pi->notes)) {
                    $pi->notes = json_decode($pi->notes, true);
                }

                if ($this->option('debug') && $this->option('verbose')) {
                    info(print_r($pi->toArray(), true));
                }

                if (
                    isset($pi->notes)
                    && isset($pi->notes['transfer_method'])
                    && $pi->notes['transfer_method'] === 'sftp'
                ) {
                    if ($this->option('debug')) {
                        $this->info('Using SFTP');
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
                } else {
                    if ($this->option('debug')) {
                        $this->info('Using FTP');
                    }
                    $config = [
                        'hostname' => $pi->hostname,
                        'username' => $pi->username,
                        'password' => $pi->password,
                        'root' => '/',
                        'port' => ($pi->provider_integration_type_id)
                            ? 21 : 22,
                        'ssl' => true,
                        'passive' => true,
                    ];
                }

                if ($this->option('debug') && $this->option('verbose')) {
                    info(print_r($config, true));
                }

                if (isset($pi->notes) && isset($pi->notes['active_customer']) && $pi->notes['active_customer']) {
                    // Active Customer List
                    $this->ftpDownload($config, 2, $brand->id, $pi->notes);
                } else {
                    $this->error('This brand is not configured to support this command.');
                }
            } else {
                $this->error('Could not locate credentials for the specified brand (' . $brand_id . ')');
            }
        } else {
            $this->error('The Brand (' . $brand_id . ') was not found.');
        }
    }

    /**
     * File FTP Download
     *
     * @param array  $config - configuration needed to perform FTP upload
     * @param string $type   - file type
     *
     * @return array
     */
    public function ftpDownload($config, $type, $brand_id, $notes)
    {
        info('FTP at ' . Carbon::now());

        $root = (isset($config['root'])) ? trim($config['root']) : '/';
        try {
            if (
                isset($notes)
                && isset($notes['transfer_method'])
                && $notes['transfer_method'] === 'sftp'
            ) {
                $adapter = new SftpAdapter(
                    [
                        'host' => $config['hostname'],
                        'port' => 22,
                        'username' => $config['username'],
                        'password' => $config['password'],
                        'root' => (isset($config['root'])) ? $config['root'] : '/',
                        'timeout' => 10,
                        'directoryPerm' => 0755,
                    ]
                );
            } else {
                $adapter = new Ftp(
                    [
                        'host' => trim($config['hostname']),
                        'username' => trim($config['username']),
                        'password' => trim($config['password']),
                        'port' => (isset($config['port'])) ? $config['port'] : 21,
                        'root' => $root,
                        'passive' => (isset($config['passive'])) ? $config['passive'] : false,
                        'ssl' => (isset($config['ssl'])) ? trim($config['root']) : false,
                        'timeout' => 20,
                    ]
                );
            }

            $filesystem = new Filesystem($adapter);

            // $type_id = ($type == 'blacklist')
            //     ? 1 : 2;

            $files = $filesystem->listContents($root);
            if ($this->option('debug')) {
                $this->info('Remote Files:');
                $this->info(print_r($files, true));
            }


            $filename = $notes['active_customer_file'];

            switch ($type) {
                case 2:
                    $contents = $filesystem->read($filename);
                    break;

                default:
                    $this->error('Unknown type: ' . $type);
                    return 42;
                    break;
            }

            if ($contents) {
                if ($this->doRestore) {
                    // mark this brand's records as unprocessed to make cleaning them up later easy
                    $start = hrtime(true);
                    CustomerList::where(
                        'brand_id',
                        $brand_id
                    )->update(
                        [
                            'processed' => 0
                        ]
                    );
                    $end_start = hrtime(true) - $start;
                    if ($this->option('verbose')) {
                        info('Done with Processed = 0 update (' . ($end_start / 1e+6) . ' ms)');
                    }
                } else {
                    DB::table('customer_lists')->where(
                        'brand_id',
                        $brand_id
                    )->where(
                        'customer_list_type_id',
                        $type
                    )->delete();
                }

                $this->processFile($contents, $brand_id, $type, $filename);

                if ($this->doRestore) {
                    // now any there were untouched we can delete safely
                    CustomerList::where(
                        'brand_id',
                        $brand_id
                    )->where(
                        'processed',
                        0
                    )->delete();
                }
            }
        } catch (\Exception $e) {
            $this->error('Error! The reason reported is: ' . $e);
        }

        return null;
    }

    public function processFile($contents, $brand_id, $type_id, $filename)
    {
        if ($this->option('verbose')) {
            $this->info('Parsing CSV file...');
        }
        $start = hrtime(true);
        $lines = explode(PHP_EOL, $contents);
        $csv = [];
        foreach ($lines as $line) {
            if (strlen(trim($line)) > 0) {
                $csv[] = str_getcsv($line);
            }
        }

        // Remove header
        unset($csv[0]);
        $endTime = hrtime(true) - $start;
        if ($this->option('verbose')) {
            $this->info('Done (' . ($endTime / 1e+6) . ' ms)');
        }

        $recordsToCreate = [];
        $recordsToRestore = [];

        if ($this->doRestore) {
            if ($this->option('verbose')) {
                $this->info('Grabbing current entries from database');
            }
            $start = hrtime(true);
            $currentList = DB::table('customer_lists')->where(
                'brand_id',
                $brand_id
            )->where(
                'customer_list_type_id',
                $type_id
            )->get();
            $endTime = hrtime(true) - $start;
            if ($this->option('verbose')) {
                $this->info('Done (' . ($endTime / 1e+6) . ' ms)');
            }
        }
        $this->info('Processing remote file: ' . $filename);
        $bar = $this->output->createProgressBar(count($csv));
        $bar->start();

        $now = now('America/Chicago');
        $start = hrtime(true);
        foreach ($csv as $c) {
            $acctNumber = ltrim(trim($c[0]), 'A');
            $rawCommodity = trim($c[2]);
            $utilityLabel = trim($c[1]);

            if (!empty($utilityLabel)) {
                $util_start = hrtime(true);
                $utility = $this->getUtilityFuel($brand_id, $utilityLabel, $rawCommodity);
                $end_util_start = hrtime(true) - $util_start;
                if ($this->option('verbose')) {
                    info('Done with Util Lookup (' . ($end_util_start / 1e+6) . ' ms)');
                }

                if ($utility) {
                    if ($this->doRestore) {
                        $cl_lookup = hrtime(true);
                        $cl = $currentList->where(
                            'utility_supported_fuel_id',
                            $utility->id
                        )->where(
                            'account_number1',
                            $acctNumber
                        )->first();
                        $end_cl_lookup = hrtime(true) - $cl_lookup;
                        if ($this->option('verbose')) {
                            info('Done with CL Lookup (' . ($end_cl_lookup / 1e+6) . ' ms)');
                        }

                        if ($cl) {
                            if (!empty($cl->deleted_at)) {
                                $recordsToRestore[] = $cl->id;
                            }
                        } else {
                            $recordsToCreate[] = [
                                'id' => Uuid::uuid4(),
                                'created_at' => $now,
                                'updated_at' => $now,
                                'customer_list_type_id' => $type_id,
                                'brand_id' => $brand_id,
                                'utility_supported_fuel_id' => $utility->id,
                                'account_number1' => $acctNumber,
                                'filename' => $filename,
                                'processed' => 1,
                            ];
                        }
                    } else {
                        $recordsToCreate[] = [
                            'id' => Uuid::uuid4(),
                            'created_at' => $now,
                            'updated_at' => $now,
                            'customer_list_type_id' => $type_id,
                            'brand_id' => $brand_id,
                            'utility_supported_fuel_id' => $utility->id,
                            'account_number1' => $acctNumber,
                            'filename' => $filename,
                            'processed' => 1,
                        ];
                    }
                }
            }

            if (!$this->doRestore && count($recordsToCreate) == 1000) {
                DB::table('customer_lists')->insert($recordsToCreate);
                $recordsToCreate = [];
            }
            $bar->advance();
        }
        $bar->finish();
        $endTime = hrtime(true) - $start;
        $this->line('');
        if ($this->option('verbose')) {
            $this->info('Done (' . ($endTime / 1e+6) . ' ms)');
        }

        if (!empty($recordsToCreate)) {
            if ($this->doRestore) {
                $this->info('Creating ' . count($recordsToCreate) . ' new records');
                $start = hrtime(true);
                $chunks = collect($recordsToCreate)->chunk(1000);
                foreach ($chunks as $chunk) {
                    DB::table('customer_lists')->insert($chunk->toArray());
                }
                $endTime = hrtime(true) - $start;
                if ($this->option('verbose')) {
                    $this->info('Done (' . ($endTime / 1e+6) . ' ms)');
                }
            } else {
                $this->info('Creating last ' . count($recordsToCreate) . ' records');
                DB::table('customer_lists')->insert($recordsToCreate);
            }
        } else {
            $this->warn('No new records to create');
        }

        if (!empty($recordsToRestore)) {
            $result = 0;
            $this->info('Restoring ' . count($recordsToRestore) . ' existing records');
            $start = hrtime(true);
            $chunks = collect($recordsToRestore)->chunk(1000);
            foreach ($chunks as $chunk) {
                $result = $result + DB::table('customer_lists')->whereIn('id', $chunk->toArray())->update([
                    'updated_at' => $now,
                    'deleted_at' => null,
                    'processed' => 1,
                ]);
            }
            $endTime = hrtime(true) - $start;
            if ($this->option('verbose')) {
                $this->info('Done (' . ($endTime / 1e+6) . ' ms)');
            }
            $this->info('Restored ' . $result . ' records.');
        } else {
            $this->info('No existing records to restore');
        }

        if (!empty($this->missingUtilities)) {
            $this->warn('Some (' . count($this->missingUtilities) . ') utilities were not found:');
            foreach ($this->missingUtilities as $key => $utilName) {
                $this->line($utilName);
            }
        }
    }

    private function getUtilityFuel(string $brand_id, string $ulabel, string $rawCommodity)
    {
        switch (trim(mb_strtolower($rawCommodity))) {
            case 'electric':
                $commodity = 1;
                break;

            case 'gas':
            case 'natural gas':
                $commodity = 2;
                break;

            default:
                $commodity = null;
                break;
        }
        $key = md5($ulabel . $brand_id . $rawCommodity);

        if ($commodity !== null) {
            if (in_array($key, $this->utilityCache)) {
                return $this->utilityCache[$key];
            }
            $val = Cache::remember(
                'cll_utility_' . $key,
                1800,
                function () use ($ulabel, $brand_id, $commodity) {
                    return UtilitySupportedFuel::select(
                        'utility_supported_fuels.*'
                    )->join(
                        'brand_utilities',
                        function ($join) use ($ulabel, $brand_id) {
                            $join->on(
                                'brand_utilities.utility_id',
                                'utility_supported_fuels.utility_id'
                            )->where(
                                'brand_utilities.utility_label',
                                $ulabel
                            )->where(
                                'brand_utilities.brand_id',
                                $brand_id
                            )->whereNull(
                                'brand_utilities.deleted_at'
                            );
                        }
                    )->where(
                        'utility_supported_fuels.utility_fuel_type_id',
                        $commodity
                    )->first();
                }
            );

            if ($val != null) {
                $this->utilityCache[$key] = $val;
                return $val;
            }
        }
        if (!in_array($key, $this->missingUtilities)) {
            $this->missingUtilities[$key] = $ulabel . ' - ' . $rawCommodity;
        }
        return null;
    }
}
