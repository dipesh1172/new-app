<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use App\Models\UtilitySupportedFuel;
use App\Models\EsiidFile;
use App\Models\Esiid;

class ErcotEsiid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ercot:esiid {--skip_update} {--include_full} {--full_only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pulls the latest incremental diff files from the ERCOT website for updating.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function emptyDir($dir)
    {
        if (is_dir($dir)) {
            $scn = scandir($dir);
            foreach ($scn as $files) {
                if ('.' !== $files && '..' !== $files) {
                    if (!is_dir($dir.'/'.$files)) {
                        unlink($dir.'/'.$files);
                    } else {
                        $this->emptyDir($dir.'/'.$files);
                        rmdir($dir.'/'.$files);
                    }
                }
            }
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $baseurl = 'http://mis.ercot.com';
        $url = '/misapp/GetReports.do?reportTypeId=203&reportTitle=TDSP%20ESI%20ID%20Extracts&showHTMLView=&mimicKey';
        $html = file_get_contents($baseurl.$url);
        $dom = new \DOMDocument('1.0', 'utf-8');
        @$dom->loadHTML($html);

        $zip = new \ZipArchive();
        $tables = $dom->getElementsByTagName('table');
        $rows = $tables->item(0)->getElementsByTagName('tr');

        foreach ($rows as $row) {
            $cols = $row->getElementsByTagName('td');
            $link = $cols->item(3);

            if (isset($link->textContent) && 'zip' === $link->textContent) {
                $result['filename'] = $cols->item(0)->nodeValue;

                if (strpos($result['filename'], '_DAILY.zip') && $this->option('full_only')) {
                    echo 'Skipping Daily file '.$result['filename']." due to --full_only\n";
                    continue;
                } else {
                    if (strpos($result['filename'], '_FUL.zip')
                        && !$this->option('include_full')
                    ) {
                        echo 'Skipping FULL file '.$result['filename']." due to include_full being false\n";
                        continue;
                    }
                }

                // If the file doesn't exist in esiid_files, we haven't imported it yet.
                $ef = EsiidFile::where(
                    'filename',
                    $result['filename']
                )->first();
                if (!$ef) {
                    echo 'Processing '.$result['filename']."\n";
                    foreach ($cols->item(3)->getElementsByTagName('a') as $atag) {
                        $result['link'] = $baseurl.$atag->getAttribute('href');
                    }

                    $path = public_path('/tmp').'/esiid/'.$result['filename'];
                    if (!file_exists($path)) {
                        file_put_contents(
                            $path,
                            fopen($result['link'], 'r')
                        );
                    }

                    if ($zip->open($path)) {
                        $dir = str_replace('.zip', '', $path);
                        $zip->extractTo($dir);
                        $zip->close();

                        foreach (glob($dir.'/*.csv') as $csv) {
                            $contents = file_get_contents($csv);
                            $lines = explode(PHP_EOL, $contents);
                            $complete = false;

                            foreach ($lines as $line) {
                                $data = str_getcsv($line);

                                if (isset($data[1])) {
                                    if (isset($data[0]) && strlen(trim($data[0])) > 0) {
                                        $e = Esiid::select(
                                            'id',
                                            'esiid_status_id'
                                        )->where(
                                            'esiid',
                                            $data[0]
                                        )->first();
                                        if (!$e) {
                                            $identifier = substr($data[0], 0, 7);
                                            $found = false;

                                            switch ($identifier) {
                                                case '1020404':
                                                    $name = 'AEP North';
                                                    $found = true;
                                                    break;
                                                case '1003278':
                                                    $name = 'AEP Central';
                                                    $found = true;
                                                    break;
                                                case '1008901':
                                                    $name = 'Centerpoint';
                                                    $found = true;
                                                    break;
                                                case '1013830':
                                                    $name = 'Nueces';
                                                    $found = false;
                                                    break;
                                                case '1044372':
                                                    $name = 'Oncor';
                                                    $found = true;
                                                    break;
                                                case '1017699':
                                                    $name = 'Oncor/SESCO';
                                                    $found = false;
                                                    break;
                                                case '1003109':
                                                    $name = 'Sharyland';
                                                    $found = false;
                                                    break;
                                                case '1017008':
                                                    $name = 'Sharyland/McAllen';
                                                    $found = false;
                                                    break;
                                                case '1040051':
                                                    $name = 'Texas New Mexico Power';
                                                    $found = true;
                                                    break;
                                                default:
                                                    $name = null;
                                                    $found = false;
                                                    break;
                                            }

                                            if (isset($name) && isset($found) && $found) {
                                                $usf = Cache::remember(
                                                    'utility_supported_fuels_'.md5($name),
                                                    300,
                                                    function () use ($name) {
                                                        return UtilitySupportedFuel::select(
                                                            'utility_supported_fuels.id'
                                                        )->leftJoin(
                                                            'utilities',
                                                            'utility_supported_fuels.utility_id',
                                                            'utilities.id'
                                                        )->where(
                                                            'utilities.name',
                                                            $name
                                                        )->first();
                                                    }
                                                );
                                                if ($usf) {
                                                    Esiid::disableAuditing();

                                                    $e = new Esiid();
                                                    $e->utility_supported_fuel_id = $usf->id;
                                                    $e->esiid = trim($data[0]);
                                                    // $e->street_number = trim($street_address);
                                                    $e->address = trim(
                                                        preg_replace(
                                                            '/\s\s+/',
                                                            ' ',
                                                            str_replace(
                                                                "\n",
                                                                ' ',
                                                                $data[1]
                                                            )
                                                        )
                                                    );
                                                    $e->city = trim($data[3]);
                                                    $e->state = trim($data[4]);
                                                    $e->zipcode = trim($data[5]);
                                                    $e->market_id = ('Residential' !== $data[15])
                                                        ? 2 : 1;
                                                    $e->esiid_status_id = ('Active' === $data[8])
                                                        ? 1 : 2;
                                                    $e->save();

                                                    Esiid::enableAuditing();

                                                    echo '-- Inserted '.$data[0]."\n";

                                                    $complete = true;
                                                }
                                            }
                                        } else {
                                            if (!$this->option('skip_update')) {
                                                echo '-- Updated '.$data[0]."\n";

                                                switch ($data[8]) {
                                                    case 'Active':
                                                        $status = 1;
                                                        break;
                                                    case 'De-Energized':
                                                        $status = 2;
                                                        break;
                                                    default:
                                                        $status = 3;
                                                }

                                                if ($e->esiid_status_id !== $status) {
                                                    $e->esiid_status_id = $status;
                                                    $e->save();
                                                }
                                            }

                                            $complete = true;
                                        }
                                    }
                                }
                            }

                            if ($complete) {
                                $ef = new EsiidFile();
                                $ef->filename = $result['filename'];
                                $ef->save();
                            }

                            if (file_exists($dir)) {
                                $this->emptyDir($dir);
                                rmdir($dir);
                            }
                        }
                    } else {
                        echo 'Error extracting '.$path."\n";
                    }
                } else {
                    echo 'Skipping already processed -- '.$result['filename']."\n";
                }
            }
        }
    }
}
