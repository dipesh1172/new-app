<?php

namespace App\Console\Commands;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Carbon\CarbonImmutable;
use App\Models\StatsProduct;
use App\Models\GpsDistance;
use App\Models\GpsCoord;
use App\Models\Event;
use App\Models\Address;

class CalculateDistanceSAClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:distance:sa:client {--confcode= } {--start_date=} {--end_date=} {--nightly}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the distance between the Sales Agent and the client';

    protected $client = null; // Instance of GuzzleHttp\Client

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
        // Create a new instance of GuzzleHttp\Client, can be recycled for many requests (faster to reuse one connection)
        $this->client = new Client();

        $confcode = $this->hasOption('confcode') ? $this->option('confcode') : null;
        if ($confcode === null) {
            $start_date = ($this->option('start_date'))
                ? CarbonImmutable::parse($this->option('start_date'))
                : CarbonImmutable::now('America/Chicago')->subMinutes(30);
            $end_date = ($this->option('end_date'))
                ? CarbonImmutable::parse($this->option('end_date'))
                : CarbonImmutable::now('America/Chicago');

            if ($this->option('nightly')) {
                $start_date = $end_date->subDay()->startOfDay();
                $end_date = $end_date->subDay()->endOfDay();
            }

            $sp = StatsProduct::select(
                'stats_product.gps_coords',
                'stats_product.event_id',
                'stats_product.service_address1',
                'stats_product.service_address2',
                'stats_product.service_city',
                'stats_product.service_state',
                'stats_product.service_zip',
                'stats_product.service_country',
                'stats_product.eztpv_id'
            )->leftJoin(
                'events',
                'stats_product.event_id',
                'events.id'
            )->whereNotNull(
                'stats_product.eztpv_id'
            )->whereBetween(
                'stats_product.event_created_at',
                [
                    $start_date,
                    $end_date
                ]
            )->where(
                'events.sa_distance',
                0
            )->where(
                'events.channel_id',
                1
            )->get();
        } else {
            //by conf code
            $sp = StatsProduct::select(
                'stats_product.gps_coords',
                'stats_product.event_id',
                'stats_product.service_address1',
                'stats_product.service_address2',
                'stats_product.service_city',
                'stats_product.service_state',
                'stats_product.service_zip',
                'stats_product.service_country',
                'stats_product.eztpv_id'
            )->join(
                'events',
                'stats_product.event_id',
                'events.id'
            )->whereNotNull(
                'stats_product.gps_coords'
            )->where(
                'stats_product.confirmation_code',
                $confcode
            )->groupBy('stats_product.event_id')
                ->get();
        }

        foreach ($sp as $sproduct) {
            $address = implode(' ', [$sproduct->service_address1, $sproduct->service_address2, $sproduct->service_city, $sproduct->service_state, $sproduct->service_zip]);

            if ($sproduct->service_country && !is_numeric($sproduct->service_country) && strlen($sproduct->service_country) > 2) {
                $address .= ' ' . $sproduct->service_country;
            }

            $addr = Address::where('line_1', $sproduct->service_address1)
                ->where('line_2', $sproduct->service_address2)
                ->where('city', $sproduct->service_city)
                ->where('zip', $sproduct->service_zip)
                ->with('gps_coordinates')
                ->first();

            $lat1 = null;
            $lon1 = null;

            $gpsAddr = null;

            if (!empty($addr) && !empty($addr->gps_coordinates)) {
                $gpsAddr = $addr->gps_coordinates;
                $looked_up_loc = explode(',', $gpsAddr->coords);
                if (count($looked_up_loc) == 2) {
                    $lat1 = floatval($looked_up_loc[0]);
                    $lon1 = floatval($looked_up_loc[1]);
                }
            }

            if (!empty($addr) && !empty($addr->suggested_line_1)) {
                $suggestedCoords = GpsCoord::where('ref_type_id', 5)
                    ->where('gps_coord_type_id', 8)
                    ->where('type_id', $addr->id)
                    ->first();

                if ($suggestedCoords) {
                    $looked_up_loc = explode(',', $suggestedCoords->coords);
                    if (count($looked_up_loc) == 2) {
                        $sugLat = floatval($looked_up_loc[0]);
                        $sugLon = floatval($looked_up_loc[1]);

                        if (!empty($sproduct->gps_coords)) {
                            $gps_coords = explode(',', $sproduct->gps_coords);
                            $lat2 = floatval($gps_coords[0]);
                            $lon2 = floatval($gps_coords[1]);

                            $dis = $this->distance($sugLat, $sugLon, $lat2, $lon2);

                            $disRec = GpsDistance::where('type_id', $sproduct->event_id)
                                ->where('ref_type_id', 3)
                                ->where('distance_type_id', 5)
                                ->first();

                            if (empty($disRec)) {
                                info('Added gps_distance (SA to suggested) entry for event ' . $sproduct->event_id);
                                $disRec = new GpsDistance();
                                $disRec->created_at = now('America/Chicago');
                                $disRec->updated_at = now('America/Chicago');
                                $disRec->type_id = $sproduct->event_id;
                                $disRec->ref_type_id = 3;
                                $disRec->distance_type_id = 5;
                                $disRec->gps_point_a = $suggestedCoords->id;
                                $disRec->gps_point_b = $sproduct->gps_coords;
                                $disRec->distance = $dis;
                                $disRec->save();
                            }
                        }
                    }
                }
            }

            if ($lat1 == null || $lon1 == null) {
                $geo = $this->getGoogleGeoInfo($address);
                if ($geo !== null) {
                    if (isset($geo->status) && $geo->status == 'OK') {
                        $lat1 = floatval($geo->results[0]->geometry->location->lat); // Latitude (lat)
                        $lon1 = floatval($geo->results[0]->geometry->location->lng); // Longitude (lng)

                        if ($addr) {
                            $gpsAddr = new GpsCoord();
                            $gpsAddr->created_at = now('America/Chicago');
                            $gpsAddr->updated_at = now('America/Chicago');
                            $gpsAddr->coords = $lat1 . ',' . $lon1;
                            $gpsAddr->ref_type_id = 5;
                            $gpsAddr->gps_coord_type_id = 7;
                            $gpsAddr->type_id = $addr->id;
                            $gpsAddr->api_type = $this->getGoogleApiType($geo, $address);
                            $gpsAddr->api_response = json_encode($geo);
                            $gpsAddr->save();
                        }
                    } else {
                        Log::error('Non-OK geolocate status for event: ' . $sproduct->event_id, [!empty($geo->status) ? $geo->status : '$geo is empty']);
                    }
                } else {
                    Log::error('Bad status code for GET request event_id:' . $sproduct->event_id . 'on address' . $address);
                }
            }

            if ($lat1 != null && $lon1 != null && !empty($sproduct->gps_coords)) {
                $gps_coords = explode(',', $sproduct->gps_coords);
                $lat2 = floatval($gps_coords[0]);
                $lon2 = floatval($gps_coords[1]);
                $agentLoc = GpsCoord::where('ref_type_id', 1)->where('gps_coord_type_id', 2)->where('type_id', $sproduct->eztpv_id)->first();
                if (empty($agentLoc)) {
                    $agentLoc = new GpsCoord();
                    $agentLoc->created_at = now('America/Chicago');
                    $agentLoc->updated_at = now('America/Chicago');
                    $agentLoc->ref_type_id = 1;
                    $agentLoc->type_id = $sproduct->eztpv_id;
                    $agentLoc->gps_coord_type_id = 2;
                    $agentLoc->coords = $sproduct->gps_coords;
                    $agentLoc->save();
                }
                $dis = $this->distance($lat1, $lon1, $lat2, $lon2);
                $e = Event::find($sproduct->event_id);
                if ($e) {
                    $e->sa_distance = $dis;
                    $e->save();

                    if ($gpsAddr && $agentLoc) {
                        $disRec = GpsDistance::where('type_id', $e->id)->where('ref_type_id', 3)->where('distance_type_id', 1)->first();
                        if (empty($disRec)) {
                            info('Added gps_distance (SA) entry for event ' . $sproduct->event_id);
                            $disRec = new GpsDistance();
                            $disRec->created_at = now('America/Chicago');
                            $disRec->updated_at = now('America/Chicago');
                            $disRec->type_id = $e->id;
                            $disRec->ref_type_id = 3;
                            $disRec->distance_type_id = 1;
                            $disRec->gps_point_a = $gpsAddr->id;
                            $disRec->gps_point_b = $agentLoc->id;
                            $disRec->distance = $dis;
                            $disRec->save();
                        } else {
                            info('Updated gps_distance (SA) entry for event ' . $sproduct->event_id);
                            $disRec->distance = $dis;
                            $disRec->gps_point_a = $gpsAddr->id;
                            $disRec->gps_point_b = $agentLoc->id;
                            $disRec->save();
                        }
                    }
                }
            } else {
                Log::error('No gps_coords for event_id: ' . $sproduct->event_id);
            }
        }
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

    protected function getGoogleGeoInfo($address)
    {
        // Guard clause, we can only process strings here
        if (!$address || gettype($address) != 'string') {
            Log::error("getGoogleGeoInfo expects a string address.", ['address'=>$address,'type'=>gettype($address)]);
            return null; 
        }

        // Address string cleanup
        $address = preg_replace("/[^A-Za-z0-9 ]/", '', $address); // Remove any non alphanumeric characters, "Apt #123" appears to cause inaccurate responses
        $address = preg_replace('!\s+!', ' ', $address);          // Replace several spaces '  ' with single space ' ', may show in report, cleaner visibility

        $apiKey = 'AIzaSyDy5TT2nXryphrixfocnA3jMzXtBWN4PGY'; // Google maps now requires an API key.
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $apiKey;
        $cacheKey = 'geo-lookup-' . md5($url);
        return Cache::remember($cacheKey, 60 * 24, function () use ($url, $address) {
            try {
                $res = $this->client->get($url);
                if ($res->getStatusCode() == 200) {
                    $result = json_decode((string) $res->getBody());

                    if (!$this->isGeoInfoValid($result)) {
                        $this->info("GoogleAPIS Address Lookup found no results for address: '$address'");
                        return null;
                    }
    
                    return $result;
                } else {
                    return null;
                }
            } catch (\Exception $e) {
                info('Exception while asking google for ' . $url, [$e]);
                return null;
            }
        });
    }

    protected function distance($lat1, $lon1, $lat2, $lon2)
    {
        return CalculateDistanceFromGPS($lat1, $lon1, $lat2, $lon2);
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

    /** sortResultsByGeolocationAccuracy
     * $result - Raw Response from Google Maps API
     * * SEE: https://developers.google.com/maps/documentation/geocoding/requests-reverse-geocoding
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
        $order = ['ROOFTOP','RANGE_INTERPOLATED','GEOMETRIC_CENTER','APPROXIMATE'];
        // DESTRUCTIVE, ALTERS original Array, sorts multiple results so most accurate results are first in results
        usort($result->results, function ($a, $b) use ($order) {
            if ($a->geometry->location_type == $b->geometry->location_type) return 0;
            $pos_a = array_search($a->geometry->location_type, $order);
            $pos_b = array_search($b->geometry->location_type, $order);
            return (($pos_a < $pos_b) ? -1 : 1);
        });
    }

}
