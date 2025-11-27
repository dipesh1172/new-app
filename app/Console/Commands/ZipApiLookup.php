<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use App\Models\ZipCode;

class ZipApiLookup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zip:lookup {--zip=} {--dryrun} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Looks up zip information and if needed updates our database';

    private $http;
    private $apikey;
    private $dryrun;
    private $force;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->http = new Client([
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'tpv.com http client/1.0',
                'Accept' => 'application/json',
            ],
        ]);
        $this->apikey = config('services.zipcodes.key');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->dryrun = $this->option('dryrun') === true;
        $this->force = $this->option('force') === true;

        if ($this->dryrun && $this->force) {
            $this->error('Incompatible options dryrun and force cannot be used together.');
            return 2;
        }

        if (empty($this->apikey)) {
            $this->error('Zipcode API Key is missing');
            return 99;
        }

        $zip = (string)($this->option('zip'));

        $existing = ZipCode::where('zip', $zip)->first();
        if (!empty($existing) && !$this->force) {
            $this->error('ZipCode already exists and will not be updated.');
            return 1;
        }

        if (!empty($existing) && $this->force) {
            $this->warn('Existing Record will be update');
        }

        if (strlen($zip) === 5) {
            $country = 1;
        }

        if (strlen($zip) === 6) {
            $country = 2;
        }

        if (!isset($country)) {
            $this->error('Invalid Zip Length, must be 5 for US and 6 for Canada');
            return 43;
        }

        $requestUrl = 'https://api.zip-codes.com/ZipCodesAPI.svc/1.0/GetZipCodeDetails/' . $zip . '?key=' . $this->apikey;

        $response = $this->http->get($requestUrl);

        $statusCode = $response->getStatusCode();

        if ($this->option('verbose')) {
            $this->info('Status code: ' . $statusCode);
            $this->line((string)$response->getBody());
        }

        if ($statusCode === 200) {
            try {
                $zipResponse = json_decode((string)($response->getBody()), true);

                if (isset($zipResponse['Error'])) {
                    throw new \Exception($zipResponse['Error']);
                }

                if (empty($zipResponse['item'])) {
                    throw new \Exception('Item not found in response');
                }

                $item = $zipResponse['item'];

                if (!empty($existing)) {
                    $newZip = $existing;
                } else {
                    $newZip = new ZipCode();
                }

                $newZip->zip = $zip;
                $newZip->city = $item['City'];
                if ($country === 1) {
                    $newZip->state = $item['State'];
                } else {
                    $newZip->state = $item['Province'];
                }

                if (!empty($item['CountyName'])) {
                    $newZip->county = $item['CountyName'];
                }

                $newZip->country = $country;
                $newZip->lat = floatval($item['Latitude']);
                $newZip->lon = floatval($item['Longitude']);
                $newZip->timezone = $this->getTimezone($item['TimeZone']);
                $newZip->dst = $item['DayLightSaving'] === 'Y';

                if (!$this->dryrun) {
                    $newZip->save();
                } else {
                    $this->info('Would have created/updated zip:');
                    $this->line(json_encode($newZip));
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                return 42;
            }
        } else {
            $this->error('Got Status: ' . $statusCode);
            return 42;
        }
        return 1;
    }

    private function getTimezone(string $tz): int
    {
        switch ($tz) {
            case '8':
                return -8;
            case '7':
                return -7;
            case '6':
                return -6;
            case '5':
                return -5;
            default:
                return 0;
        }
    }
}
