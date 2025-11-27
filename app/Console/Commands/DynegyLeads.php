<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Lead;

use Ramsey\Uuid\Uuid;
use Illuminate\Console\Command;

class DynegyLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dynegy:leads {--file=} {--blacklist} {--whitelist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dynegy Lead Loading';

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
            echo "Syntax: php artisan dynegy:leads --file=<path to file>\n";
            exit();
        }

        if (!$this->option('whitelist') && !$this->option('blacklist')) {
            echo "You must specify one of --whitelist or --blacklist\n";
            exit();
        }

        ini_set('auto_detect_line_endings', true);

        $lead_type_id = ($this->option('blacklist'))
            ? 1 : 2;
        $write = fopen(
            "/tmp/leads" . $lead_type_id . ".sql",
            "a"
        ) or die("Unable to open file!");
        $file = $this->option('file');
        if (file_exists($file)) {
            $brand = Brand::select('id')->where(
                'name',
                'Dynegy LLC'
            )->first();
            
            $explode = explode('/', $file);
            $campaign = rtrim($explode[count($explode) - 1], ".csv");
            $handle = fopen($file, 'r');
            $header = null;

            while ($row = fgetcsv($handle)) {
                if ($header === null) {
                    $header = $row;
                    continue;
                }

                $data = array_combine($header, $row);

                $ref_code = trim($data['Dynegy_Reference_Code']);
                $first_name = trim($data['FIRST_NAME']);
                $middle_name = (strlen(trim($data['MIDDLE_INITIAL'])) > 0)
                    ? trim($data['MIDDLE_INITIAL']) : null;
                $last_name = trim($data['LAST_NAME']);
                $company_name = (isset($data['COMPANY_NAME']))
                    ? trim($data['COMPANY_NAME']) : null;
                $address1 = trim($data['SERVICE_ADDRESS']);
                $address2 = (strlen(trim($data['SUITE_APT'])) > 0)
                    ? trim($data['SUITE_APT']) : null;
                $city = trim($data['SERVICE_CITY']);
                $state = trim($data['SERVICE_STATE']);
                $zip = trim($data['SERVICE_ZIP']);
                $zip4 = (strlen(trim($data['SERVICE_ZIP4'])) > 0)
                    ? trim($data['SERVICE_ZIP4']) : null;

                $ref_code_start = substr($ref_code, 0, 2);
                $ref_code_end = ($lead_type_id == 1)
                    ? substr($ref_code, -8)
                    : substr($ref_code, -10);
                $ref_code_final = $ref_code_start . "" . $ref_code_end;

                switch (trim($data['DWELLING_TYPE'])) {
                    case 'SINGLE FAMILY':
                        $home_type_id = 1;
                        break;
                    case 'MULTI FAMILY':
                        $home_type_id = 2;
                        break;
                    default:
                        $home_type_id = 4;
                }

                $sql = 'INSERT IGNORE INTO leads (id, created_at, updated_at, brand_id, lead_type_id, external_lead_id, company_name, first_name, middle_name, last_name, service_address1, service_address2, service_city, service_state, service_zip, service_zip4, home_type_id, lead_campaign) VALUES ("' . Uuid::uuid4() . '", NOW(), NOW(), "' . $brand->id . '", ' . $lead_type_id . ', "' . $ref_code_final . '", "' . $company_name . '", "' . $first_name . '", "' . $middle_name . '", "' . $last_name . '", "' . $address1 . '", "' . $address2 . '", "' . $city . '", "' . $state . '", "' . $zip . '", "' . $zip4 . '", ' . $home_type_id . ', "' . $campaign . '")';
                fwrite($write, $sql . ";\n");
            }
        } else {
            echo "Specified file does not exist.\n";
        }

        fclose($handle);
        fclose($write);
    }
}
