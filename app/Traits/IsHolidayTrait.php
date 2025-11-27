<?php

namespace App\Traits;

use Carbon\Carbon;

trait IsHolidayTrait
{
    /**
     * Returns true if the given date is a U.S. holiday, else returns false.
     * 
     * @param Carbon $date The date to check.
     * 
     * @return Boolean True if the given date falls on a U.S. holiday, else false.
     */
    private function isUsHoliday(Carbon $date)
    {
        if (!isset($date)) {
            return false;
        }

        $holidays = $this->getUsHolidays($date->year);

        foreach ($holidays as $holiday) {
            if ($holiday['date'] == $date) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns an array containing a list of U.S. holidays for a given year.
     * 
     * @param Integer $year The year to return the list of holidays for.
     * 
     * @return mixed An array containg a list of U.S. holidays.
     */
    private function getUsHolidays(int $year)
    {
        $holidays = [];

        // New Year's Day
        $holidays[] = [
            'name' => 'New Years Day',
            'date' => Carbon::parse($year . '-01-01')
        ];

        // Martin Luther King Jr.
        // Observed 3rd Monday of Jan.
        $date = Carbon::parse($year . '-01-01')->nthOfMonth(3, Carbon::MONDAY);
        $holidays[] = [
            'name' => 'Martin Luther King Jr.',
            'date' => $date
        ];

        // Presidents' Day
        // Observed 3rd Monday of Feb.
        $date = Carbon::parse($year . '-02-01')->nthOfMonth(3, Carbon::MONDAY);
        $holidays[] = [
            'name' => 'Presidents Day',
            'date' => $date
        ];

        // Memorial Day
        // Observed last Monday of May.
        $date = Carbon::parse($year . '-06-01')->previous(Carbon::MONDAY); // Start in June and get prev Monday.
        $holidays[] = [
            'name' => 'Memorial Day',
            'date' => $date
        ];

        // Independence Day
        $holidays[] = [
            'name' => 'Independence Day',
            'date' => Carbon::parse($year . '-07-04')
        ];

        // Labor Day
        // Observed 1st Monday of Sept.
        $date = Carbon::parse($year . '-09-01')->nthOfMonth(1, Carbon::MONDAY);
        $holidays[] = [
            'name' => 'Labor Day',
            'date' => $date
        ];

        // Columbus Day
        // Observed 2nd Monday of Oct.
        $date = Carbon::parse($year . '-10-01')->nthOfMonth(2, Carbon::MONDAY);
        $holidays[] = [
            'name' => 'Columbus Day',
            'date' => $date
        ];

        // Veterans Day
        $holidays[] = [
            'name' => 'Veterans Day',
            'date' => Carbon::parse($year . '-11-11')
        ];

        // Thanksgiving Day
        // Observed 4th Thursday of Nov.
        $date = Carbon::parse($year . '-11-01')->nthOfMonth(4, Carbon::THURSDAY);
        $holidays[] = [
            'name' => 'Thanksgiving Day',
            'date' => $date
        ];

        // Christmas Day
        $holidays[] = [
            'name' => 'Christmas Day',
            'date' => Carbon::parse($year . '-12-25')
        ];

        return $holidays;
    }
}
