<?php

namespace App\Console\Commands;

use League\Flysystem\Filesystem;
use League\Flysystem\Config;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Survey;
use App\Models\Script;
use App\Models\ProviderIntegration;
use App\Models\PhoneNumberLookup;
use App\Models\PhoneNumber;
use App\Models\Brand;

class ImportEntelSurveys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'entel:import {--cached : Pull from cached file instead of FTP}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the survey data from the Entel FTP site.';

    /**
     * The filename to store the cached data in.
     *
     * @var string
     */
    protected $cache_file = 'entel_survey_data.csv';

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
        $brand = Brand::where(
            'name',
            'Entel Marketing LLC'
        )->orWhere(
            'name',
            'Entel Marketing'
        )->first();
        if (!$brand) {
            echo "Unable to find an Entel Marketing brand.\n";
            exit();
        }

        $brand_id = $brand->id; // "69BE6C56-767B-4551-86EF-F586330A3891";
        $env_id = config('app.env') === 'production' ? 1 : 2;
        $this->info('Running in '.config('app.env'));

        if (!$this->option('cached') || !file_exists($this->cache_file)) {
            $pi = ProviderIntegration::where(
                'brand_id',
                $brand->id
            )->where(
                'env_id',
                $env_id
            )->first();
            if (!$pi) {
                echo 'Unable to get provider integration credentials.';
                exit();
            }

            $config = [
                'host' => $pi->hostname,
                'username' => $pi->username,
                'password' => $pi->password,
                'port' => 21,
                'root' => '/',
                'ssl' => false,
                'passive' => true,
                'timeout' => 30,
            ];

            $adapter = new Ftp($config);
            $filesystem = new Filesystem(
                $adapter,
                new Config(
                    [
                        'disable_asserts' => true,
                    ]
                )
            );

            $files = $filesystem->listContents('/');
            $this->info('Processing '.count($files).' filesytem entries');
            foreach ($files as $file) {
                if ($file['type'] === 'dir' || $file['extension'] !== 'csv') {
                    $this->comment('Skipping '.$file['path'].' as unprocessable');
                    continue;
                }
                $contents = $filesystem->read($file['path']);
                $this->process_file($brand_id, $contents);
                //file_put_contents($this->cache_file, $contents);
            }
        } else {
            $contents = file_get_contents($this->cache_file);
            $this->process_file($brand_id, $contents);
        }
    }

    private function process_file($brand_id, $contents)
    {
        $lines = explode(PHP_EOL, $contents);
        $csv = [];
        foreach ($lines as $line) {
            if (strlen(trim($line)) > 0) {
                $csv[] = str_getcsv($line);
            }
        }

        $header = null;
        $this->info('Processing '.(count($csv) - 1).' records.');
        foreach ($csv as $row) {
            if ($header === null) {
                $header = $row;
                continue;
            }

            $data = array_combine($header, $row);
            $this->import_survey($brand_id, $data);
        }
    }

    private function import_survey($brand_id, $survey)
    {
        // print_r($survey);
        // exit();

        $script = Script::where('brand_id', $brand_id)->first();
        if ($script) {
            $survey['ProductAmount'] = utf8_encode($survey['ProductAmount']);
            foreach ($survey as $key => $value) {
                $snake = Str::snake($key);
                unset($survey[$key]);
                $survey[$snake] = $value;
            }

            // print_r($survey);
            // exit();

            $custom_data = $survey;
            unset($custom_data['user_first_name']);
            unset($custom_data['user_last_name']);
            // unset($custom_data['custom_start_date']);

            $phone = null;
            if (isset($survey['customer_telephone']) && strlen(trim($survey['customer_telephone'])) > 0) {
                $phone = CleanPhoneNumber($survey['customer_telephone']);
            }

            if ($phone === null || strlen($phone) !== 12) {
                if ($phone !== null) {
                    $this->error('Invalid phone number: '.$survey['customer_telephone']);
                }

                return;
            }

            $pnl = PhoneNumberLookup::where(
                'phone_number_type_id',
                6
            )->join(
                'phone_numbers',
                'phone_numbers.id',
                'phone_number_lookup.phone_number_id'
            )->where(
                'phone_numbers.phone_number',
                $phone
            )->get();

            if (!$pnl->isEmpty()) {
                $this->info('Phone Number Lookup record exists: '.$phone);

                return;
            }

            $s = new Survey();
            $s->brand_id = $brand_id;
            $s->customer_first_name = $survey['user_first_name'];
            $s->customer_last_name = $survey['user_last_name'];
            $s->customer_enroll_date = Carbon::yesterday();
            $s->script_id = $script->id;
            $s->language_id = strtolower($survey['language']) === 'english' ? 1 : 2;
            $s->agency = $survey['client_name'];
            $s->custom_data = json_encode($custom_data);
            $s->save();

            if ($s) {
                if (isset($phone)) {
                    $exists = PhoneNumber::where(
                        'phone_number',
                        $phone
                    )->withTrashed()->first();
                    if (!$exists) {
                        $pn = new PhoneNumber();
                        $pn->phone_number = $phone;
                        $pn->save();
                        $pnid = $pn->id;
                    } else {
                        $pnid = $exists->id;
                        if ($exists->trashed()) {
                            $exists->restore();
                        }
                    }

                    $pnl = new PhoneNumberLookup();
                    $pnl->phone_number_type_id = 6;
                    $pnl->type_id = $s->id;
                    $pnl->phone_number_id = $pnid;
                    $pnl->save();
                }
            }

            // print_r($s->toArray());
            // exit();
        }
    }
}
