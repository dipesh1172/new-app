<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use App\Models\Vendor;
use App\Models\State;
use App\Models\Office;
use App\Models\EztpvConfig;
use App\Models\Channel;
use App\Models\Brand;

class OfficeConfig extends Command
{
    protected $headers = [
        'vendor',
        'office',
        'state',
        'channel',
        'live_call',
        'digital',
        'voice_capture',
        'contract',
        'preview_contract',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'office:config
        {--brand=}
        {--state=}
        {--channel=}
        {--dump-to-csv}
        {--dump-to-json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Office configuration';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function lookupChannel($channel_id) {
        return Cache::remember(
            'channel-' . trim($channel_id),
            86400,
            function () use ($channel_id) {
                return Channel::find($channel_id);
            }
        );
    }

    public function lookupState($state_id) {
        return Cache::remember(
            'state-' . trim($state_id),
            86400,
            function () use ($state_id) {
                return State::find($state_id);
            }
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $time_start = microtime(true);

        if (!$this->option('brand')) {
            $this->info('Syntax: php artisan office:config --brand=<brand id>');
            exit();
        }

        $brand = Brand::find($this->option('brand'));
        if (!$brand) {
            $this->warn('Invalid brand id specified.');
            exit();
        }

        $vendors = Vendor::where(
            'brand_id',
            $brand->id
        )->orderBy(
            'vendors.vendor_label'
        )->get();
        if ($vendors) {
            foreach ($vendors as $vendor) {
                $offices = Office::where(
                    'vendor_id',
                    $vendor->id
                )->orderBy(
                    'offices.name'
                )->get();
                if ($offices) {
                    foreach ($offices as $office) {
                        $ezConfig = EztpvConfig::where(
                            'office_id',
                            $office->id
                        )->first();
                        if ($ezConfig) {
                            if (is_string($ezConfig->config)) {
                                $cs = json_decode($ezConfig->config, true);
                            }

                            if (!empty($cs)) {
                                foreach ($cs as $state => $v1) {
                                    $s = $this->lookupState($state);

                                    if (isset($v1['channels'])) {
                                        foreach ($v1['channels'] as $channel => $v2) {
                                            $live_call = (!empty($v2['live_call'])
                                                && intval($v2['live_call']) === 1)
                                                ? 'Y'
                                                : 'N';
                                            $digital = (!empty($v2['digital'])
                                                && intval($v2['digital']) === 1)
                                                ? 'Y'
                                                : 'N';
                                            $voice_capture = (!empty($v2['voice_capture'])
                                                && intval($v2['voice_capture']) === 1)
                                                ? 'Y'
                                                : 'N';
                                            $contract = (!empty($v2['contract'])
                                                && intval($v2['contract']) === 2)
                                                ? 'Y'
                                                : 'N';
                                            $preview_contract = (!empty($v2['preview_contract'])
                                                && intval($v2['preview_contract']) === 1)
                                                ? 'Y'
                                                : 'N';

                                            $c = $this->lookupChannel($channel);

                                            $data[] = [
                                                $vendor->vendor_label,
                                                $office->name,
                                                optional($s)->state_abbrev,
                                                optional($c)->channel,
                                                $live_call,
                                                $digital,
                                                $voice_capture,
                                                $contract,
                                                $preview_contract,
                                            ];
                                        }
                                    } else {
                                        $data[] = [
                                            $vendor->vendor_label,
                                            $office->name,
                                            optional($s)->state_abbrev,
                                            null,
                                            'N',
                                            'N',
                                            'N',
                                            'N',
                                            'N',
                                        ];
                                    }
                                }
                            } else {
                                $data[] = [
                                    $vendor->vendor_label,
                                    $office->name,
                                    null,
                                    null,
                                    'N',
                                    'N',
                                    'N',
                                    'N',
                                    'N',
                                ];
                            }
                        } else {
                            $data[] = [
                                $vendor->vendor_label,
                                $office->name,
                                null,
                                null,
                                'N',
                                'N',
                                'N',
                                'N',
                                'N',
                            ];
                        }
                    }
                }
            }

            $newdata = [];
            if ($this->option('state')) {
                foreach ($data as $d) {
                    if (isset($d[2]) && strtolower($d[2]) === strtolower($this->option('state'))) {
                        $newdata[] = $d;
                    }
                }

                $data = $newdata;
            }

            $newdata = [];
            if ($this->option('channel')) {
                foreach ($data as $d) {
                    if (isset($d[3]) && strtolower($d[3]) === strtolower($this->option('channel'))) {
                        $newdata[] = $d;
                    }
                }

                $data = $newdata;
            }

            if ($this->option('dump-to-csv')) {
                $filename = '/tmp/export-' . time() . '.csv';
                $file = fopen($filename, 'w');

                fputcsv($file, $this->headers);

                foreach ($data as $d) {
                    fputcsv($file, $d);
                }

                echo "Export File = " . $filename . "\n";
            } elseif ($this->option('dump-to-json')) {
                $json = [];
                foreach ($data as $d) {
                    $json[] = array_combine($this->headers, $d);
                }

                echo json_encode($json);
            } else {
                $this->info('Brand = ' . $brand->name);
                $this->table($this->headers, $data);
                $this->info('Total Execution Time: ' . (microtime(true) - $time_start) . ' sec(s)');
            }
        }
    }
}
