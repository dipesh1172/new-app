<?php

namespace App\Console\Commands\Tests;

use Illuminate\Console\Command;
use App\Models\StatsProduct;
use GuzzleHttp\Client;
use App\Models\GpsDistance;
use App\Models\GpsCoord;

// This is ONLY for running experimental tests.  THIS DOES NOT WRITE DATA
class TestAddressToGps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:addressToGps {--debug} {--debug_end} {--max=} {--show_every=} {--address=} {--sleep=} {--address_chk=}';
    /*
        --debug         : Use to display questionable results in colection
        --max=          : Integer, required to check addresses in Stats Product table, max number of results (limit 5000)
        --show_every=   : Output results every N (integer) results
        --address=      : String address - '123 Test Ave Anytown, MA 12345'
        --sleep=        : sleeps for this many seconds (integer) to pause so you can read the results
        --address_chk   : Used with Debugger, set a breakpoint
        --debug_end     : Debug at End on the last result

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Command for Address to Geolocation';
    protected $client = null;
    protected $count = 0;
    protected $null_addresses = [];
    protected $invalid_addresses = [];
    protected $multi_match = [];
    protected $show_every = 100;
    protected $location_types = ['ROOFTOP' => 0, 'GEOMETRIC_CENTER' => 0, 'RANGE_INTERPOLATED' => 0, 'APPROXIMATE' => 0]; // Used as a Statistic Counter
    protected $locations = ['ROOFTOP' => [], 'GEOMETRIC_CENTER' => [], 'RANGE_INTERPOLATED' => [], 'APPROXIMATE' => []];
    protected $debug = false;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->client = new Client();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->debug = $this->option('debug');

        if ($this->option('address')) {
            $address = $this->option('address');
            $geo = $this->getGoogleGeoInfo($address);
            $this->echoResults($address, $geo);
            return;
        }

        if ($this->option('max')) {
            if (!is_numeric($this->option('max'))) { throw new \Exception("Error: --max expects an Integer"); }
            if ($this->option('max') > 5000)       { throw new \Exception("Error: --max Limit is 5000"); }

            if ($this->option('sleep') && !is_numeric($this->option('sleep'))) { throw new \Exception("Error: --sleep expects an Integer"); }

            if ($this->option('show_every')) {
                if (!is_numeric($this->option('show_every'))) { throw new \Exception("Error: --show_every expects an Integer"); }
                $this->show_every = (int)$this->option('show_every');
            }            

            $stats_products = $this->getStatsProducts();

            foreach ($stats_products as $sp) {
                $address = implode(' ', [$sp->service_address1, $sp->service_address2, $sp->service_city, $sp->service_state, $sp->service_zip, $sp->service_country]);

                if ($this->option('address_chk') && preg_match('/' . $this->option('address_chk') . '/', $address) ) {
                    // Set a Debugger Breakpoint on this line to step though the results and examine data
                    echo "Address check for '$address' located\n";
                }

                $geo = $this->getGoogleGeoInfo($address);

                if (!$geo) { $this->null_addresses[] = $address . " NULL"; }
                if ($geo) {
                    if ($geo->results[0]->geometry->location_type == 'APPROXIMATE') { $this->invalid_addresses[] = $address . " APPROXIMATE"; }
                    if (count($geo->results) > 1) { 
                        // Add to Array of addresses from Stats Product that produce multiple results, such as "123 Test" but there is a "123 Test Ave", and "123 Test St"
                        $this->multi_match[] = $address;

                        // Experiment on Accuracy
                        $accuracies = [];
                        foreach ($geo->results as $r){
                            $accuracies[] = $this->calculateGpsAccuracyRating($r);
                        }
                        
                    }

                    if (count($geo->results) > 0) {
                    // Increment Type Counter                        
                        $this->location_types[$geo->results[0]->geometry->location_type]++; 
                        // Groups Addresses by Location Type to identify patterns.  Use with Debugger and Breakpoints
                        $this->locations[$geo->results[0]->geometry->location_type][] = $address;
                    }
                }


                if ($this->count && $this->count % $this->show_every == 0){ $this->showTotals(); }
                $this->count++;

                $this->echoResults($address, $geo);
            }

            if ($this->option('debug_end')) {
                $this->debug = true;
            }

            echo "------------------\n";
            $this->showTotals();

            // Use with Debugger breakpoints to highlight to see values.  Otherwise this does NOTHING.
            $this->locations['ROOFTOP'];
            $this->locations['RANGE_INTERPOLATED'];
            $this->locations['GEOMETRIC_CENTER'];
            $this->locations['APPROXIMATE'];

            return;
        }

        echo "Please use --address='123 Test Ave Anytown MA 12345' or --max=10 (number) for stats_product results ";
    }

    // Returns a float value, the lower, the more accurate
    protected function calculateGpsAccuracyRating($google_coord) : float
    {
        /* THIS IS EXPERIMENTAL

        Concept here, if we do not have a ROOFTOP type of location, such as either GEOMETRIC_CENTER or RANGE_INTERPOLATED, we have a "Viewport"
        which has a northeast and southwest coordinate.  The less accurate, the bigger the area.  For example, we plug in a wrong address, the
        "viewport" we get back might contain all of New York City, very high number so its a very low accuracy.

        NOTE: We should OMIT results here and not even bother if we have TWO ROOFTOP RESULTS.  Trouble is there are multiple matches, such
        a to coords for "123 Test" as our search value but get back two results, one for "123 Test Ave" and one for "123 Test St", both of
        which may be ROOFTOPS.  So in cases like that, we do not know which set of coords to use.

        The idea is that if we do not have one precise location, see which one provides us with the most accurate results based on the
        minimal area.  GEOMETRIC_CENTER for example might be the size of an entire street, all of Main St in some town, which might be several
        miles long, thus high distance so low degree of accuracy.

        The Float value that is returned here might be used in the USORT function.
        */

        $lat1 = $google_coord->geometry->viewport->northeast->lat;
        $lng1 = $google_coord->geometry->viewport->northeast->lng;
        $lat2 = $google_coord->geometry->viewport->southwest->lat;
        $lng2 = $google_coord->geometry->viewport->southwest->lng;
        return CalculateDistanceFromGPS($lat1, $lng1, $lat2, $lng2);
    }    

    protected function echoResults($address, $geo)
    {
        $string_result = ($this->option('max') ? "$this->count => " : '');
        $lat_lon = ($geo ? $geo->results[0]->geometry->location->lat . ',' . $geo->results[0]->geometry->location->lng : null);
        $geo_type = ($geo ? $geo->results[0]->geometry->location_type : 'No Result');

        $string_result .= "'$address', '$lat_lon', '$geo_type'\n";
        echo $string_result;
    }

    protected function showTotals()
    {
        $lt = "ROOFTOP: " . $this->location_types['ROOFTOP'];
        $lt .= ", RANGE_INTERPOLATED: " . $this->location_types['RANGE_INTERPOLATED'];
        $lt .= ", GEOMETRIC_CENTER: " . $this->location_types['GEOMETRIC_CENTER'];
        $lt .= ", APPROXIMATE: " . $this->location_types['APPROXIMATE'];

        if (count($this->invalid_addresses) > 0) {
            echo "\nNull Addresses: ";
            echo ($this->debug ? print_r($this->null_addresses, true) : count($this->null_addresses));            
            echo "\nInvalid Addresses: ";
            echo ($this->debug ? print_r($this->invalid_addresses, true) : count($this->invalid_addresses));
            echo "\nAddresses With Multiple Results: ";
            echo ($this->debug ? print_r($this->multi_match, true) : count($this->multi_match));
            echo "\nTotal: $this->count, $lt\n";
        }
        else {
            echo "\nTotal: $this->count, Invalid Addresses: 0, Addresses with Multiple Results: " . count($this->multi_match) . " " . $lt;
        }

        echo "\n\n";

        if ($this->option('sleep')) sleep($this->option('sleep'));
    }

    private function getStatsProducts() {
        return StatsProduct::select(
            'stats_product.service_address1',
            'stats_product.service_address2',
            'stats_product.service_city',
            'stats_product.service_state',
            'stats_product.service_zip',
            'stats_product.service_country'
        )
        ->whereNotNull('stats_product.service_address1')
        ->whereNotNull('stats_product.service_city')
        ->whereNotNull('stats_product.service_state')
        ->whereNotNull('stats_product.service_zip')
        ->orderBy('stats_product.created_at', 'DESC')
        ->limit($this->option('max'))
        ->get();
    }    

    // ===  Code Below used for Address to GPS Location Lookup from Google API as well as dependent helper functions

    /** getGoogleGeoInfo($address) 
     * 
     */
    protected function getGoogleGeoInfo($address)
    {
        // Guard clause, we can only process strings here
        if (!$address || gettype($address)!= 'string'){ 
            info(__FILE__ . " Error: getGoogleGeoInfo expects a string address.", ['address'=>$address,'type'=>gettype($address)]);
            return null; 
        }

        $address = preg_replace("/[^A-Za-z0-9 ]/", '', $address); // Remove any non alphanumeric characters
        $address = preg_replace('!\s+!', ' ', $address);          // Replace several spaces '  ' with single space ' '

        $apiKey = 'AIzaSyDy5TT2nXryphrixfocnA3jMzXtBWN4PGY'; // Google maps now requires an API key.
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $apiKey;
        try {
            $res = $this->client->get($url);
            if ($res->getStatusCode() == 200) {
                $result = json_decode((string) $res->getBody());

                if ($this->isGeoInfoValid($result)){
                    // Sort the results so most accurate result is first in array when more than one location is found IF MORE THAN ONE RESULT
                    if (count($result->results) > 1) { $this->sortResultsByGeolocationAccuracy($result); }

                    return $result;
                }
            } 

            $this->info("GoogleAPIS Address Lookup found no results for address: '$address', Status Code:" . $res->getStatusCode());
            return null;
        } catch (\Exception $e) {
            info('Exception while asking google for ' . $url, [$e]);
            return null;
        }
    }

    /** sortResultsByGeolocationAccuracy
     * $result - Raw Response from Google Maps API
     * - The response from Google is an array called "results".  The "results" do not appear to be sorted to favor Accuracy.  This attempts to
     *   sort the array so the location_type with the highest degree of accuracy is first.
     * 
     * - "ROOFTOP" indicates that the returned result is a precise geocode for which we have location information accurate down to street address precision.
     * 
     * - "RANGE_INTERPOLATED" indicates that the returned result reflects an approximation (usually on a road) interpolated between two precise 
     *   points (such as intersections). Interpolated results are generally returned when rooftop geocodes are unavailable for a street address.
     * 
     * - "GEOMETRIC_CENTER" indicates that the returned result is the geometric center of a result such as a polyline (for example, a street) or polygon (region)."
     * 
     * - "APPROXIMATE" indicates that the returned result is approximate.
     */
    protected function sortResultsByGeolocationAccuracy($result)
    {
        // Order of Accuracy - https://developers.google.com/maps/documentation/geocoding/requests-reverse-geocoding
        $order = ['ROOFTOP','RANGE_INTERPOLATED','GEOMETRIC_CENTER','APPROXIMATE'];
        // DESTRUCTIVE, ALTERS referenced Array, sorts multiple results so most accurate results are first in results
        usort($result->results, function ($a, $b) use ($order) {
            if ($a->geometry->location_type == $b->geometry->location_type) return 0;
            $pos_a = array_search($a->geometry->location_type, $order);
            $pos_b = array_search($b->geometry->location_type, $order);
            return (($pos_a < $pos_b) ? -1 : 1);
        });
    }

    /** isGeoInfoValid($loc) : $loc is a Raw Response from Google Maps API
     * - Multiple conditions checked.  Each condition to return false is on its own line so you can tell exactly
     *   what caused a condition to fail.
     * - This allows for cleaner code.  If this were inline code inside a single nested if statement: if (cond1 || !cond2 || !cond3), it becomes
     *   unclear as to what condition triggered the failure
     */
    protected function isGeoInfoValid($loc) : bool
    {
        // Why use a function, and why multiple "return false" and not use an OR statement?  So you can step thru and tell which condition it fails on
        if (!$loc)                                                         return false;
        if (!property_exists($loc, 'results'))                             return false;
        if (!is_array($loc->results))                                      return false;
        if (count($loc->results) == 0)                                     return false;
        if (!property_exists($loc->results[0], 'geometry'))                return false;
        if (!property_exists($loc->results[0]->geometry, 'location'))      return false;
        if (!property_exists($loc->results[0]->geometry, 'location_type')) return false;
        if (!property_exists($loc->results[0]->geometry->location, 'lat')) return false;
        if (!property_exists($loc->results[0]->geometry->location, 'lng')) return false;

        return true;
    }    

}
