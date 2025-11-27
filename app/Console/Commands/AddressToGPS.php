<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use App\Models\GpsCoord;
use App\Models\Address;
use App\Models\Country;

class AddressToGPS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gps:lookup {--addressid=} {--line1=} {--line2=} {--city=} {--zip=} {--state=} {--use-suggested=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Looks up the GPS coordinates for an address and updates the database if necessary';

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
        $addressId = $this->option('addressid');
        $useSuggested = $this->option('use-suggested');

        if (empty($addressId)) {
            $this->info('Lookup up existing address by address');
            $line1 = $this->option('line1');
            $line2 = $this->option('line2');
            $city = $this->option('city');
            $state = $this->option('state');
            $zip = $this->option('zip');
            $addr = Address::where('line_1', $line1)
                ->where('line_2', $line2)
                ->where('city', $city)
                ->where('state_province', $state)
                ->where('zip', $zip)
                ->first();
        } else {
            $this->info('Looking up existing address by id');
            $addr = Address::find($addressId);
            if ($addr) {
                $this->info('Address found');
                if ($useSuggested && $addr->suggested_line_1) {
                    $this->info('Using suggested address');
                    $line1 = $addr->suggested_line_1;
                    $line2 = $addr->suggested_line_2;
                    $city = $addr->suggested_city;
                    $state = $addr->suggested_state_province;
                    $zip = $addr->suggested_zip;
                } else {
                    $line1 = $addr->line_1;
                    $line2 = $addr->line_2;
                    $city = $addr->city;
                    $state = $addr->state_province;
                    $zip = $addr->zip;
                }
            } else {
                $this->error('Could not locate that address in the database');
                return 42;
            }
        }

        $gps_coords = null;
        if ($addr) {
            if ($useSuggested) {
                $gps_coords = GpsCoord::where('ref_type_id', 5)
                    ->where('gps_coord_type_id', 8) // 8 for suggested address coords
                    ->where('type_id', $addr->id)
                    ->first();
            } else {
                $gps_coords = GpsCoord::where('ref_type_id', 5)
                    ->where('gps_coord_type_id', 7) // 7 for entered address coords
                    ->where('type_id', $addr->id)
                    ->first();
            }
        }

        if ($gps_coords) {
            $this->info('Location already resolved as:');
            $this->line($gps_coords->coords);
        } else {
            $this->info('No GPS, resolving...');
            $country = '';
            if ($addr && $addr->country_id) {
                $cr = Country::find($addr->country_id);
                if ($cr) {
                    $country = $cr->country;
                }
            }
            $geo = $this->getGoogleGeoInfo($line1, $line2, $city, $state, $zip, $country);

            if ($geo !== null && isset($geo->status) && $geo->status == 'OK') {
                $this->info('GPS is valid');
                $lat1 = floatval($geo->results[0]->geometry->location->lat); // Latitude
                $lon1 = floatval($geo->results[0]->geometry->location->lng);

                $api_address = implode(' ', [$line1, $line2, $city, $state, $zip]);
                $api_address = preg_replace('!\s+!', ' ', $api_address);

                if ($addr && empty($gps_coords)) {
                    $gpsAddr = new GpsCoord();
                    $gpsAddr->created_at = now('America/Chicago');
                    $gpsAddr->updated_at = now('America/Chicago');
                    $gpsAddr->coords = $lat1 . ',' . $lon1;
                    $gpsAddr->ref_type_id = 5;
                    $gpsAddr->gps_coord_type_id = $useSuggested ? 8 : 7; // 8 for suggested address coords, 7 for entered address
                    $gpsAddr->type_id = $addr->id;
                    $gpsAddr->api_type = $this->getGoogleApiType($geo, $api_address);
                    $gpsAddr->api_response = json_encode($geo);                    
                    $gpsAddr->save();
                }
                $this->info('Location resolved to:');
                $this->line(json_encode($geo, \JSON_PRETTY_PRINT));
            } else {
                $this->info('GPS is not valid');
                $this->line(json_encode($geo));
            }
        }
    }

    protected function getGoogleGeoInfo($address1, $address2, $city, $state, $zip, $country = '')
    {
        $address = implode(' ', [$address1, $address2, $city, $state, $zip, $country]);
        // Address string cleanup
        $address = preg_replace("/[^A-Za-z0-9 ]/", '', $address); // Remove any non alphanumeric characters, "Apt #123" appears to cause inaccurate responses
        $address = preg_replace('!\s+!', ' ', $address);          // Replace several spaces '  ' with single space ' ', may show in report, cleaner visibility

        $address = urlencode($address);

        $apiKey = 'AIzaSyDy5TT2nXryphrixfocnA3jMzXtBWN4PGY'; // Google maps now requires an API key.
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . $address . '&key=' . $apiKey;
        $cacheKey = 'google-geo-lookup-' . md5($url);
        return Cache::remember($cacheKey, 60 * 24, function () use ($url, $address) {
            $client = new Client();
            try {
                $res = $client->get($url);
                if ($res->getStatusCode() == 200) {
                    $this->info('Got a good response from google');
                    $result = json_decode((string) $res->getBody());

                    if (!$this->isGeoInfoValid($result)) {
                        $this->info("GoogleAPIS Address Lookup found no results for address: " . urldecode($address));
                        return null;
                    }
    
                    return $result;
                } else {
                    $this->info('got a bad response from google');
                    return null;
                }
            } catch (\Exception $e) {
                info('Exception while asking google for ' . $url, [$e]);
                return null;
            }
        });
    }

    /** isGeoInfoValid($loc) : $loc is a Raw Response from Google Maps API
     * - Multiple conditions checked.  Each condition to return false is on its own line so you can tell exactly
     *   what caused a condition to fail.
     * - This allows for cleaner code.  If this were inline code inside a single nested if statement: if (cond1 || !cond2 || !cond3), it
     *   becomes unclear as to what condition triggered the failure
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

    protected function getGoogleApiType($geo, $addr) : string
    {
        // Location Type from Google Maps API ['ROOFTOP','RANGE_INTERPOLATED','GEOMETRIC_CENTER','APPROXIMATE'];
        $result = $geo->results[0]->geometry->location_type;

        // Reporting that there is more than one result found helps identify why some reports display inaccurate distances when wrong location used
        // IE "123 Main St" is input but there is both "123 North Main St" and "123 South Main St"
        if (count($geo->results) > 1) {
            $result .= ', Multiple Results Found, Total ' . count($geo->results);
        }

        // Including the address itself helps explain why we get some Non 'ROOFTOP' Addresses, typos "123Main St" or "123 Mane3 St", test data, etc.
        $result .= " for '$addr'";

        // DB limit for this field is 255 characters, include 0
        $result = substr($result, 0, 254);

        return $result;
    }    
}
