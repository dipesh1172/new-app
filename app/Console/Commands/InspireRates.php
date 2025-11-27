<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Exception;
use App\Models\UtilitySupportedFuel;
use App\Models\Utility;
use App\Models\State;
use App\Models\RateType;
use App\Models\Rate;
use App\Models\ProviderIntegration;
use App\Models\Product;
use App\Models\Brand;

class InspireRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inspire:rates
        {--cache : Cache rate output}
        {--dryrun : Dont make changes}
        {--debugLevel=0 : 0=No Debug Output, 1=All Output, 2=No Variable Dumps, 3=Only Show Progress}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspire Rates API';


    /**
     * Name of the file to cache the rates in locally.
     *
     * @var string
     */
    private $rates_filename = "inspire.rates.json";

    /**
     * Rates types array, pulled from the DB on startup.
     *
     * @var array
     */
    private $rate_types = [];

    /**
     * Products that have already been processed this run.
     *
     * @var array
     */
    private $products_seen = [];

    /**
     * Rates that have already been processed this run.
     *
     * @var array
     */
    private $rates_seen = [];

    /**
     * States array, pulled from the DB on startup.
     *
     * @var array
     */
    private $state = [];

    /**
     * Term types array, pulled from the DB on startup.
     *
     * @var array
     */
    private $term_types = []; // TODO

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
        $brand = Brand::where(
            'name',
            'like',
            'Inspire%'
        )->whereNotNull('client_id')->first();

        $this->debug("Brand ID: " . $brand->id);

        $rt = RateType::all();
        $rt->each(function ($r) {
            $this->rate_types[$r->rate_type] = $r->id;
        });

        $s = State::all();
        $s->each(function ($sc) {
            $this->states[$sc->state_abbrev] = $sc->id;
        });

        /*
        $tt = TermType::all();
        $tt->each(function ($t)
        {
            $this->term_types[$t->term_type] = $t->id;
        });
        */

        $env_id = config('app.env') === 'production' ? 1 : 2;
        $this->debug("Running in " . config('app.env'));

        $pi = ProviderIntegration::where('brand_id', $brand->id)->where('env_id', $env_id)->first();
        $hostname = $pi->hostname . "/api/1/clients/DXC3C269/offers/RESI/LOCAL_DEALERS";
        $username = $pi->username;
        $password = $pi->password;

        if (file_exists($this->rates_filename) && $this->option('cache')) {
            $this->debug('Loading Rates from Cache file');
            $info = json_decode(file_get_contents($this->rates_filename), true);
        } else {
            $info = $this->rest_get_offers($hostname, $username, $password);
            if ($this->option('cache')) {
                $this->info('Rates saved to Cache file');
                file_put_contents($this->rates_filename, json_encode($info));
            }
        }

        DB::beginTransaction();

        $this->debug('Removing existing products');
        $existing = Product::where('brand_id', $brand->id)->whereNull('deleted_at')->get();
        $existing->each(function ($p) {
            Rate::where('product_id', $p->id)->delete();
            $p->delete();
        });

        try {
            $this->startProgressBar(count($info));
            foreach ($info as $offer) {
                $this->processOffer($brand->id, $offer);
            }
            $this->stopProgressBar(count($info));

            if ($this->option('dryrun')) {
                throw new Exception('Test run');
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->error($e->getFile() . ' (' . $e->getLine() . '): ' . $e->getMessage());
        }
    }

    private function rest_get_offers($url, $username, $password)
    {
        if ($url == null || $username == null || $password == null) {
            $this->error('Please check the configuration for Inspire Rate API');
        }

        $client = new Client();
        $userAgent =  'TPV.com Focus Rate Import/0.9';
        $res = $client->request('GET', $url, [
            'auth' => [$username, $password],
            'headers' => [
                'User-Agent' => $userAgent,
                'Accept'     => 'application/json'
            ]
        ]);

        $results = json_decode($res->getBody(), true);

        info(print_r($results, true));
        info($res->getBody());

        // exit();

        return $results;
    }

    private function processOffer($brand_id, $o)
    {
        // print_r($o);
        // exit();

        $this->advanceProgressBar();
        $p = $this->productOrNew($brand_id, $o['plan_name'], $o);
        $u = $this->utility($o);
        if (!$u) {
            throw new Exception("Unable to find the utility [" . $o['market_name'] . "]");
        } else {
            $r = $this->rateOrNew($p->id, $u->id, $o['offer_code'], $o);
        }
    }

    private function productOrNew($brand_id, $name, $o)
    {
        $cacheName = 'product_' . $brand_id . '_' . $name;
        if (array_key_exists($cacheName, $this->products_seen)) {
            $this->debugVar("Product has already been seen this run. [" . $name . "]");
            return $this->products_seen[$cacheName];
        }

        $p = Cache::remember(
            $cacheName,
            120,
            function () use ($brand_id, $name, $o) {
                return Product::where([
                    ['brand_id', $brand_id],
                    ['name', $name]
                ])->withTrashed()->first();
            }
        );

        if ($p) {
            if ($p->trashed()) {
                $this->debug("Restoring Product");
                $p->restore();
            }
        } else {
            $p = new Product();
            $p->brand_id = $brand_id;
            $p->name = $o['plan_name'];
            $p->channel = "DTD"; // $o['channel_code'];
            $p->market = "Residential";
            $p->home_type = "Single|Multi-Family|Apartment";
            if (array_key_exists($o['contract_type'], $this->rate_types)) {
                $p->rate_type_id = $this->rate_types[$o['contract_type']];
            } else {
                $this->debug("Couldn't find rate_type[" . $o['contract_type'] . "]");
                $p->rate_type_id = 0;
            }
            $p->green_percentage = $o['energy_sources']['sources'][0]['percent'];
            $p->term = $o['contract_duration_months'];
            $p->term_type_id = 3; // TODO
            $p->service_fee = $o['customer_service_fee'];
            // $p->daily_fee = ;
            // $p->monthly_fee = ;
            // $p->promo_code = ;
            // $p->source_code = ;
            // $p->renewal_plan = ;
            // $p->channel_source = ;
            // $p->prepaid = ;
            // $p->dxc_rec_id = ;
            $p->date_from = $o['offer_start_dt'];
            $p->date_to = $o['offer_end_dt'];
            // $p->intro_daily_fee = ;
            // $p->intro_service_fee = ;
            // $p->intro_term = ;
            $p->intro_term_type_id = 3; // TODO
            // $p->custom_fields = ;
            // $p->transaction_fee = ;
            // $p->transaction_fee_currency_id = ;
            $p->hidden = 0; // TODO

            $p->save();
        }

        $this->products_seen[$cacheName] = $p;

        $this->debugVar("Product: ");
        $this->debugVar($p->toArray());

        return $p;
    }

    private function utility($o)
    {
        switch (strtolower($o['product_type_code'])) {
            case 'electric':
                $fuel = 1;
                break;
            case 'natural gas':
            case 'gas':
                $fuel = 2;
                break;
        }

        $usf = Utility::select(
            'utility_supported_fuels.id',
            'states.name AS state_name'
        )->leftJoin(
            'utility_supported_fuels',
            'utilities.id',
            'utility_supported_fuels.utility_id'
        )->leftJoin(
            'states',
            'utilities.state_id',
            'states.id'
        )->where(
            'utility_supported_fuels.utility_fuel_type_id',
            $fuel
        )->where(
            'utilities.name',
            'LIKE',
            '%' . $o['market_name'] . '%'
        )->whereNull(
            'utilities.deleted_at'
        )->whereNull(
            'utility_supported_fuels.deleted_at'
        )->first();
        if ($usf) {
            return $usf;
        }

        return null;
    }

    private function rateOrNew($product_id, $utility_id, $offer_code, $o)
    {
        $cacheName = 'rate_' . $product_id . '_' . $utility_id . '_' . $offer_code;
        if (array_key_exists($cacheName, $this->rates_seen)) {
            $this->debugVar("Rate has already been seen this run. [" . $product_id . ", " . $utility_id . ", " . $offer_code . "]");
            return $this->rates_seen[$cacheName];
        }

        $r = Cache::remember(
            $cacheName,
            120,
            function () use ($product_id, $utility_id, $offer_code) {
                return Rate::where([
                    // ['product_id', $product_id],
                    ['utility_id', $utility_id],
                    ['program_code', $offer_code]
                ])->withTrashed()->first();
            }
        );

        if ($r) {
            if ($r->trashed()) {
                $this->debug("Restoring Rate");
                $r->restore();
            }
        } else {
            $r = new Rate();
            $r->product_id = $product_id;
            $r->utility_id = $utility_id;
            $r->rate_currency_id = 2; // TODO
            $r->rate_uom_id = 2; // TODO

            $ecf = $o['early_cancellation_fee'];
            if (is_numeric($ecf)) {
                $r->cancellation_fee = $ecf;
            } elseif (strpos($ecf, '$') !== false) {
                $ecf2 = str_replace('$', '', $ecf);
                if (is_numeric($ecf2)) {
                    $start = strpos($ecf, '$') + 1;
                    $end = strpos($ecf, ' ', $start) - 1;
                    $r->cancellation_fee = substr($ecf, $start, $end - $start);
                } else {
                    $start = strpos($ecf, '$') + 1;
                    $end = strpos($ecf, ' ', $start);
                    $this->debug('detected cancellation fee: ' . substr($ecf, $start, $end - $start));
                    //throw new Exception('test');

                    $r->cancellation_fee = floatval(substr($ecf, $start, $end - $start))
                        * intval($o['contract_duration_months']);
                    $r->custom_data_2 = $ecf;
                }
            } else {
                $r->cancellation_fee = 0; // TODO
            }
            // $r->cancellation_fee_currency = ;
            // $r->admin_fee = ;
            $r->external_rate_id = $o['plan_code'];
            $r->program_code = $o['offer_code'];
            try {
                $r->rate_promo_code = $o['offer_loyalty_programs'][0]['loyalty_program_code'];
            } catch (\Exception $e) {
                $r->rate_promo_code = "";
            }
            // $r->rate_source_code = ;
            // $r->rate_renewal_plan = ;
            // $r->rate_channel_source = ;
            $r->rate_amount = $o['intro_rate']; // TODO
            // $r->rate_monthly_fee = ;
            // $r->dxc_rec_id = ;
            $r->date_from = $o['offer_start_dt'];
            $r->date_to = $o['offer_end_dt'];
            $r->intro_rate_amount = $o['intro_rate'];
            // $r->intro_cancellation_fee = ;
            // $r->intro_cancelled_fee_currency = ;
            // $r->tranche = ;
            // $r->ratemap = ;
            // $r->start_month = ;
            // $r->hidden = ;
            $r->dual_only = 0; // TODO
            $r->tlp = 0; // TODO
            $r->custom_data_1 = $o['plan_rewards_description'];
            // $r->custom_data_2 = ;
            // $r->custom_data_3 = ;
            // $r->custom_data_4 = ;
            // $r->custom_data_5 = ;
            // $r->postalcode_validation = ;
            // $r->raw_postalcodes = ;
            // $r->scripting = ;
            // $r->recission = ;

            $r->save();
        }

        $this->rates_seen[$cacheName] = $r;

        $this->debugVar("Rate: ");
        $this->debugVar($r->toArray());

        return $r;
    }

    private function getStateID($sc)
    {
        if (array_key_exists($sc, $this->states)) {
            return $this->states[$sc];
        }

        $this->info("Unable to find state_id for " . $sc);
        return 0;
    }

    private function sameProduct($p1, $p2)
    {
        return true;
    }

    private function sameRate($r1, $r2)
    {
        return true;
    }

    private function debug($msg)
    {
        if ($this->option('debugLevel') == 1 || $this->option('debugLevel') == 2) {
            $this->info($msg);
        }
    }

    private function debugVar($var)
    {
        if ($this->option('debugLevel') == 1) {
            $this->debug(print_r($var, true));
        }
    }

    private function startProgressBar($count)
    {
        if ($this->option('debugLevel') == 3) {
            $this->output->progressStart($count);
        }
    }
    private function advanceProgressBar()
    {
        if ($this->option('debugLevel') == 3) {
            $this->output->progressAdvance();
        }
    }

    private function stopProgressBar()
    {
        if ($this->option('debugLevel') == 3) {
            $this->output->progressFinish();
        }
    }
}
