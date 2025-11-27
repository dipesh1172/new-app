<?php
namespace App\Console\Commands;

use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use App\Models\Brand;
use App\Models\BrandUser;
use App\Models\Office;
use App\Models\Vendor;

class FixMigrationImportDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:migration-import-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $brand_id = '200979d8-e0f5-41fb-8aed-e58a91292ca0';
    private $labels = [];
    private $missing = [];
    private $vendors = [];
    private $aliases = [
        'TXU' => [
            'SAG',
            'ITS',
            'TXD',
            'TXU Internal - Dallas',
            'SEC',
        ],
        'SMI' => 'SMI Energy',
        'MMM' => 'MMM_MARKETING',
        'THS' => ['JML','Total Home Solutions'],
        'MBM' => 'Millennium Brilliant Minds LLC',
        'GES' => 'General Energy Services LLC',
        'TKH' => 'TKH Enterprises',
        'QGL' => 'SMI Energy',
        'RGV' => ['JML','GRG'],
        'TCC' => 'JML',
    ];
    private $solidCache = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->solidCache = array_flip(array_flatten($this->aliases));

        foreach($this->solidCache as &$value) {
            $value = [];
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        # Lists the CSV files and takes the brand-name/office-label out of the filename
        $this->label_csv_files('public/tmp/TXU Agents - with dt_added');

        printf("Labels-to-filepath found:\n%s\n\n", trimar($this->labels));

        # Gets all office ID's associated with brand name in file for TXU (configured by $this->brand_id)
        $this->get_brand_ids();

        # Ensures all of our office ID's are unique and nothing wonky is going on
        if ($this->redundancy_check()) {
            foreach($this->labels as $filename => $brandLabel) {
                if (!is_array($brandLabel)) {
                    $this->load_file($filename, $brandLabel);
                }
            }
            foreach($this->labels as $filename => $brandLabel) {
                if (is_array($brandLabel)) {
                    $this->load_file($filename, $brandLabel);
                }
            }
        }

        foreach($this->solidCache as $label => $cached) {
            printf("%s: %d\n", $label, count($cached));
        }
    }

    private function load_file($filepath, $brandLabel) {
        $csvHandle = csvFile::load($filepath);
        $keyValuePairs = $csvHandle->getColumnKeyPairs('tsr_id', 'dt_added');
        $keys = array_keys($keyValuePairs);
        $brandUsers = BrandUser::select('id', 'tsr_id');
        $logBrandName = is_array($this->labels[$filepath]) ? implode(',', $this->labels[$filepath]) : $this->labels[$filepath];

        if (is_array($brandLabel)) {
            $brandIds = [];
            $knownUsers = [];

            foreach ($brandLabel as $label) {
                $brandIds[] = $this->vendors[$label];

                if (!empty($this->solidCache[$label])) {
                    printf("Found cache for: %s\n", $label);
                    $knownUsers = array_merge($knownUsers, $this->solidCache[$label]);
                }
            }

            $knownUsers = array_unique($knownUsers);
            printf("Running with cached items: [%s] %d\n", $logBrandName, count($knownUsers));

            $brandUsers = $brandUsers->
                whereIn('employee_of_id', $brandIds)->
                whereNotIn('id', $knownUsers);
        }
        else {
            printf("Running without cache: %s\n", $brandLabel);
            $brandUsers = $brandUsers->where('employee_of_id', $this->vendors[$brandLabel]);
        }

        $brandUsers = $brandUsers->where('works_for_id', $this->brand_id)->
            whereIn('tsr_id', $keys)->
            withTrashed()->
            get();

        $keyCount = count($keys);
        $userCount = count($brandUsers);

        if ($keyCount !== $userCount) {
            Log::info('ERROR ABOVE THIS^^^^');
            printf("[WARNING] Querying for brand name: %s\n", $logBrandName);
            printf("[WARNING] Total users in CSV file: %d\n", $keyCount);
            printf("[WARNING] Total users found: %d\n\n", $userCount);

            $this->missing[] = $filepath;
        }
        else {
            printf("User IDs found: [%s] %d\n", $logBrandName, $userCount);
        }

        $this->updateUsers($brandUsers, $keyValuePairs);

        if (!is_array($this->labels[$filepath])) {
            if (isset($this->solidCache[$this->labels[$filepath]])) {
                foreach($brandUsers as $user) {
                    $this->solidCache[$this->labels[$filepath]][] = $user['id'];
                }
            }
        }
    }

    private function updateUsers($brandUsers, $keyValuePairs) {
        $cases = [];
        $ids = [];
        $params = [];

        foreach ($brandUsers as $user) {
            $cases[] = "WHEN '{$user->id}' then ?";
            $params[] = mdy2ymd($keyValuePairs[$user->tsr_id]);
            $ids[] = $user->id;
        }

        $ids = implode('\',\'', $ids);
        $cases = implode(' ', $cases);

        if (!empty($ids)) {
            \DB::update("UPDATE `brand_users` SET `created_at` = CASE `id` {$cases} END WHERE `id` in ('{$ids}')", $params);
        }
    }

    private function brand_ids($brandLabels) {
        $brandIds = [];

        if (!is_array($brandLabels)) {
            $brandLabels = [$brandLabels];
        }

        foreach ($brandLabels as $brandLabel) {
            $brandIds[] = $this->vendors[$brandLabel];
        }

        return $brandIds;
    }

    private function label_csv_files($directory) {
        $fileCount = 0;
        printf("Checking directory: %s\n", getcwd() . $directory);

        foreach (glob($directory."/*") as $filepath) {
            $filename = explode('/', $filepath);
            $filename = array_pop($filename);
            $labelFromFile = explode(' - ', $filename)[3];
            $label = $this->labelName($labelFromFile);
            $this->labels[$filepath] = $label;
            ++$fileCount;

            printf("Found file label: [%s] %s -> %s\n", $labelFromFile, is_array($label) ? implode(',', $label) : $label, $filename);
        }

        printf("Files found: %d\n\n", $fileCount);
    }

    private function labelName($labelFromFile) {
        return empty($this->aliases[$labelFromFile]) ? $labelFromFile : $this->aliases[$labelFromFile];
    }

    private function get_brand_ids() {
        $labelKeys = array_unique(array_flatten(array_values($this->labels)));

        printf("Getting brand IDs for: %s\n", implode(', ', $labelKeys));

        $brands = Brand::select('brands.id', 'brands.name')->whereIn('brands.name', $labelKeys)->orderBy('brands.name')->get();
        $this->add_each_vendor($brands, 'name', 'id');
    }

    private function add_each_vendor($vendorList, $labelName, $idName) {
        for($vendorIndex=0,$vendorCount=count($vendorList); $vendorIndex<$vendorCount; ++$vendorIndex) {
            $vendorLabel = $vendorList[$vendorIndex][$labelName];

            if (!empty($this->vendors[$vendorLabel])) {
                printf("[WARNING] Label is being overwritten: %s -> %s (old)\n", $vendorLabel, $this->vendors[$vendorLabel]);
                print("[WARNING] That may cause unexpected results\n");
            }

            $this->vendors[$vendorLabel] = $vendorList[$vendorIndex][$idName];
            printf("Current label: %s -> %s\n", $vendorLabel, $vendorList[$vendorIndex][$idName]);
        }

        printf("Vendors added: %d\n\n", $vendorCount);
    }

    private function redundancy_check() {
        $flatArray = array_flatten($this->vendors);

        $totalVendorYield = count($flatArray);
        printf("Total vendors before unique check: %d\n", $totalVendorYield);

        $uniqueFlatArray = array_unique($flatArray);
        $totalUniqueVendors = count($uniqueFlatArray);
        printf("Total unique vendors: %d\n", $totalUniqueVendors);

        $this->missing = $this->filter_known_labels();

        if (count($this->missing) > 0) {
            printf("Still missing vendor ID's for: %s\n\n", implode(', ', $this->missing));
        }

        return $totalVendorYield === $totalUniqueVendors;
    }

    private function filter_known_labels() {
        $labelKeys = array_values($this->labels);
        $missingKeys = [];

        foreach($labelKeys as $labelKey) {
            if (!is_array($labelKey)) {
                $labelKey = [$labelKey];
            }

            foreach($labelKey as $label) {
                if (empty($this->vendors[$label])) {
                    $missingKeys[] = $label;
                }
            }
        }

        return array_unique($missingKeys);
    }
}

function array_flatten($array) {
    $flatArray = [];
    array_walk_recursive($array, function($value) use (&$flatArray) {
        $flatArray[] = $value;
    });
    return $flatArray;
}

function mdy2ymd($datetimeString) {
    $datetime = explode(' ', $datetimeString);
    $date = explode('-', $datetime[0]);
    $datetime[0] = implode('-', [$date[2], $date[0], $date[1]]);
    return implode(' ', $datetime);
}

function trimar($array) {
    return trim(print_r($array, true), "Array()\t\n");
}

class csvFile {
    private $filepath = null;
    private $hasHeaders = null;
    private $headers = [];
    private $rows = [];

    static function load($filepath, $hasHeaders=true) {
        return new self($filepath, !!$hasHeaders);
    }

    private function __construct($filepath, $hasHeaders) {
        $this->filepath = $filepath;
        $this->hasHeaders = $hasHeaders;
        printf("Reading file: %s\n", $filepath);

        try {
            $handle = fopen($this->filepath, "r");
            $this->setHeaders($handle);
            $this->setRows($handle);
            fclose($handle);
        }
        catch (Exception $error) {
            print("[ERROR] Cannot read the file for some reason\n");
            printf("[ERROR] %s\n", $error->getMessage());
        }
    }

    function getAllColumnRows($columnName) {
        $headerIndex = $this->headerIndex($columnName);
        $buffer = [];

        for($rowIndex=0,$rowCount=count($this->rows); $rowIndex<$rowCount; ++$rowIndex) {
            $buffer[] = $this->rows[$rowIndex][$headerIndex];
        }

        return $buffer;
    }

    function getColumnKeyPairs($keyColumnName, $valueColumnName) {
        $keyHeaderIndex = $this->headerIndex($keyColumnName);
        $valueHeaderIndex = $this->headerIndex($valueColumnName);
        $buffer = [];

        for($rowIndex=0,$rowCount=count($this->rows); $rowIndex<$rowCount; ++$rowIndex) {
            $key = $this->rows[$rowIndex][$keyHeaderIndex];
            $buffer[$key] = $this->rows[$rowIndex][$valueHeaderIndex];
        }

        return $buffer;
    }

    private function headerIndex($headerName, $isStrict=true) {
        return array_search($headerName, $this->headers, $isStrict);
    }

    private function setHeaders($handle) {
        if ($this->hasHeaders && $headers = fgetcsv($handle, 1000, ",")) {
            $this->headers = $headers;
        }
    }

    private function setRows($handle) {
        while ($rowData = fgetcsv($handle, 1000, ",")) {
            $this->rows[] = $rowData;
        }
    }
}
