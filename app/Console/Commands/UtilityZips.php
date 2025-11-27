<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Utility;

class UtilityZips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'utility:zips {--file=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Utility Zip Code Importer';

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
        if (!$this->option('file')) {
            echo "You must specify the path to a utility zips file.\n";
            exit();
        }

        $data = array_map('str_getcsv', file($this->option('file')));

        // print_r($csv);
        $zips = [];
        foreach ($data as $row) {
            $zip = str_pad($row[0], 5, "0", STR_PAD_LEFT);
            $name = $row[2];

            $zips[$name][] = $zip;
        }

        foreach ($zips as $key => $value) {
            $zips[$key] = array_unique($value);
            sort($zips[$key]);

            $name = $key;
            switch ($key) {
                case 'Duke Energy Ohio Inc':
                    $name = 'Duke Energy';
                    break;
                case 'Commonwealth Edison Co':
                    $name = "ComEd";
                    break;
                case 'Ameren Illinois Company':
                    $name = 'Ameren Energy';
                    break;
                case 'Atlantic City Electric':
                    $name = 'Atlantic City Electricity';
                    break;
                case 'Baltimore Gas & Electric Co':
                    $name = 'Baltimore Gas and Electric';
                    break;
                case 'Central Hudson Gas & Elec Corp':
                    $name = 'Central Hudson';
                    break;
                case 'Central Maine Power Co':
                    $name = 'Central Maine Power';
                    break;
                case 'Consolidated Edison Co-NY Inc':
                    $name = 'ConEd';
                    break;
                case 'Dayton Power & Light Co':
                    $name = 'Dayton Power and Light Company';
                    break;
                case 'Duquesne Light Co':
                    $name = 'Duquesne Light';
                    break;
                case 'Jersey Central Power & Lt Co':
                    $name = 'Jersey Central Power & Light';
                    break;
                case 'Metropolitan Edison Co':
                    $name = 'Metropolitan Edison Co';
                    break;
                case 'Ohio Edison Co':
                    $name = 'Ohio Edison';
                    break;
                case 'Orange & Rockland Utils Inc':
                    $name = 'Orange and Rockland';
                    break;
                case 'PECO Energy Co':
                    $name = 'PECO Energy';
                    break;
                case 'Pennsylvania Power Co':
                    $name = 'Penn Power';
                    break;
                case 'The Potomac Edison Company':
                    $name = 'Potomac Edison';
                    break;
                case 'PPL Electric Utilities Corp':
                    $name = 'PPL Electric Utilities';
                    break;
                case 'Rochester Gas & Electric Corp':
                    $name = 'Rochester Gas and Electric';
                    break;
                case 'Rockland Electric Co':
                    $name = 'Rockland Electric';
                    break;
                case 'Southern Maryland Elec Coop Inc':
                    $name = 'Southern Maryland Electric Cooperative';
                    break;
                case 'The Toledo Edison Co':
                    $name = 'Toledo Edison';
                    break;
                case 'United Illuminating Co';
                    $name = 'United Illuminating';
                    break;
                case 'West Penn Power Company':
                    $name = 'West Penn Power';
                    break;
            }

            $util = Utility::where(
                'name',
                $name
            )->first();
            if ($util) {
                echo $util->name . "\n";

                $zipstr = "";
                foreach ($zips[$key] as $zip) {
                    $zipstr .= $zip . ",";
                }

                $zipstr = rtrim($zipstr, ",");

                echo " ---- " . $zipstr . "\n";

                $util->service_zips = $zipstr;
                $util->save();
            } else {
                echo $key . " was not found...\n";
            }
        }
    }
}
