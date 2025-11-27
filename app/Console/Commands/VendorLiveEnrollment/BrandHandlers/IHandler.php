<?php

namespace App\Console\Commands\VendorLiveEnrollment\BrandHandlers;

/***
 * Live Enrollment Interface for clients
 */
interface IHandler {

    /**
     * Apply custom condition to get event records
     * @param $query
     * @return $query
     */
    public function applyCustomFilter($query);
    
    /**
     * Handler body for client
     * @param sps: StatsProduct records
     * @param options: command-line options
     * @return mixed(request, response)
     */
    public function handleSubmission($sps, $options);
    
}
