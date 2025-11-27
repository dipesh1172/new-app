<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Carbon\Carbon;

use App\Models\Brand;
use App\Models\StatsProduct;

/**
 * DXCReliantExport class.
 * 
 * Laravel command script for exporting Reliant TPV data.
 * 
 * This exported data is imported into DXC for use with billing.
 */
class DXCReliantExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dxc:reliant:export {--start-date=} {--end-date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Temporary command for formatting and exporting Reliant Energy TPV data for import into DXC\'s database.';

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
        if (!$this->option('start-date') || !$this->option('end-date')) {
            $this->error("Missing arg(s). Both --start-date and --end-date are required.");
            return -2;
        }

        // Pull the TPV data
        $this->info("Getting data...");
        $getDataResult = $this->getData();

        if ($getDataResult['result'] == "success") {

            $data = $getDataResult['data'];

            if (!$data) {
                $this->info("No data found. Exiting...");
                return -1;
            }
        } else {
            $this->error($getDataResult['message']);
            return -3;
        }

        try {
            $curRecord = 0;   // For tracking progress, console output, and checking previous record to determine if current record should keep the interaction_time or use '0.00'

            // TPVs with multiple accounts will have multiple records. Unlike in DXC, each record has a copy of the total call time. 
            // This will lead to double/triple/etc.. billing for TPVs with multiple accounts.
            // The TPV data query results are sorted by confirmation code, grouping like records together.
            // We'll track the last written confirmation code; if the current record's confirmation code matches the previous confirmation code, we write 0.00 as the call time, else interaction_time.
            $lastConfirmationCode = "";

            $progressBar = $this->output->createProgressBar(count($data));

            // Create the file
            $filename = "reliant-tpvs-export-" . time() . ".csv";
            $path = public_path("tmp/" . $filename);
            $file = fopen($path, "w");

            // Write the header row
            $headers = [
                "p_date",
                "dt_date",
                "dt_event_created",
                "center_id",
                "vendor_name",
                "office_name",
                "source",
                "focus_source",
                "focus_event_type",
                "language",
                "channel",
                "market",
                "form_name",
                "sales_state",
                "fuel_type",
                "btn",
                "email_address",
                "acct_num",
                "auth_fname",
                "auth_mi",
                "auth_lname",
                "bill_fname",
                "bill_mi",
                "bill_lname",
                "billing_address",
                "billing_city",
                "billing_state",
                "billing_zip",
                "service_address1",
                "service_address2",
                "service_city",
                "service_state",
                "service_zip",
                "service_county",
                "utility",
                "product_code",
                "brand_name",
                "ver_code",
                "tsr_id",
                "status_txt",
                "status_id",
                "dxc_rep_id",
                "call_time",
                "station_id",
                "stats_product_id"
            ];

            fputcsv($file, $headers);


            // Format and write the data
            $this->info("Formatting data");

            $progressBar->start();
            foreach ($data as $r) {
                $curRecord++;

                // Build a form name. These must match form names set up in DXC for Reliant.
                // Since we're only using this data for billing, we can use a few generic form names:
                // Reliant Energy - English (Focus) -- For standard TPVs
                // Reliant Energy - Spanish (Focus)
                // Reliant Energy - AC - English (Focus) -- For agent confirmation TPVs
                // Reliant Energy - AC - English (Focus)
                $formName = "";

                if(str_starts_with(strtolower($r->confirmation_code), "ac")) {
                    $formName = "Reliant Energy - AC - " . (empty($r->language) ? "English" : (strtolower($r->language) == "english" ? "English" : "Spanish")) . " (Focus)";
                } else {
                    $formName = "Reliant Energy - " . (empty($r->language) ? "English" : (strtolower($r->language) == "english" ? "English" : "Spanish")) . " (Focus)";
                }

                $record = [
                    "p_date" => $r->interaction_created_at->format("m-d-Y"),
                    "dt_date" => $r->interaction_created_at->format("m-d-Y H:i:s"),
                    "dt_event_created" => $r->event_created_at->format("m-d-Y H:i:s"),
                    "center_id" => $r->vendor_label,
                    "vendor_name" => $r->vendor_name,
                    "office_name" => $r->office_name,
                    "source" => 'live',
                    "focus_source" => $r->source,
                    "focus_event_type" => $r->stats_product_type,
                    "language" => strtolower($r->language),
                    "channel" => $r->channel,
                    "market" => ($r->market == "Residential" ? "Res" : "SC"),
                    "form_name" => $formName,
                    "sales_state" => $r->service_state,
                    "fuel_type" => $r->commodity,
                    "btn" => ltrim($r->btn, "+1"),
                    "email_address" => $r->email_address,
                    "acct_num" => $r->account_number1,
                    "auth_fname" => $r->auth_first_name,
                    "auth_mi" => "",
                    "auth_lname" => $r->auth_last_name,
                    "bill_fname" => $r->bill_first_name,
                    "bill_mi" => "",
                    "bill_lname" => $r->bill_last_name,
                    "billing_address" => trim($r->billing_address1 . " " . $r->billing_address2),
                    "billing_city" => $r->billing_city,
                    "billing_state" => $r->billing_state,
                    "billing_zip" => $r->billing_zip,
                    "service_address1" => $r->service_address1,
                    "service_address2" => $r->service_address2,
                    "service_city" => $r->service_city,
                    "service_state" => $r->service_state,
                    "service_zip" => $r->service_zip,
                    "service_county" => $r->service_county,
                    "utility" => $r->product_utility_name,
                    "product_code" => $r->rate_program_code,
                    "brand_name" => $r->brand_name,
                    "ver_code" => $r->confirmation_code,
                    "tsr_id" => $r->sales_agent_rep_id,
                    "status_txt" => ($r->result == "Sale" ? "good sale" : strtolower($r->result)),
                    "status_id" => ($r->disposition_label ? str_pad($r->disposition_label, 6, "0", STR_PAD_LEFT) : ""),
                    "dxc_rep_id" => $r->tpv_agent_label,
                    "call_time" => ($curRecord > 1 && $lastConfirmationCode == trim($r->confirmation_code) ? "0.00" : $r->interaction_time), // Decide whether to write 0.00 or the interaction_time.
                    "station_id" => "Focus",
                    "stats_product_id" => $r->stats_product_id
                ];

                $lastConfirmationCode = trim($r->confirmation_code);

                fputcsv($file, $record);

                $progressBar->advance();
            }
            $progressBar->finish();

            fclose($file);

            $this->info("\nExport completed!");
        } catch (\Exception $e) {

            $this->info("Exception occurred:");
            $this->info("Current Record: " . $curRecord);
            $this->info("Message: " . $e->getMessage());

            // Close and delete the file, if it exists.
            if ($file) {
                fclose($file);
                unlink($path);
            }
        }
    }


    /**
     * Retrieves the TPV data.
     */
    private function getData()
    {
        // Capture date range from args
        $start = Carbon::parse($this->option('start-date'));
        $end = Carbon::parse($this->option('end-date'));

        // Look up brand ID
        $this->info("  Brand ID lookup...");

        $brandName = "Reliant Energy Retail Services LLC";

        $brand = Brand::where('name', $brandName)->first();

        if (!$brand) {
            return ["result" => "error", "message" => "Unable to locate Brand record for '$brandName'", "data" => null];
        }

        // Get the TPV data
        $this->info("  Pulling TPV data...");

        $records = StatsProduct::select(
            'stats_product.id AS stats_product_id',
            'stats_product_types.stats_product_type',
            'stats_product.interaction_created_at',
            'stats_product.event_created_at',
            'stats_product.vendor_label',
            'stats_product.vendor_name',
            'stats_product.office_name',
            'stats_product.source',
            'stats_product.language',
            'stats_product.channel',
            'stats_product.market',
            'stats_product.service_state',
            'stats_product.commodity',
            'stats_product.btn',
            'stats_product.email_address',
            'stats_product.account_number1',
            'stats_product.auth_first_name',
            'stats_product.auth_last_name',
            'stats_product.bill_first_name',
            'stats_product.bill_last_name',
            'stats_product.billing_address1',
            'stats_product.billing_address2',
            'stats_product.billing_city',
            'stats_product.billing_state',
            'stats_product.billing_zip',
            'stats_product.service_address1',
            'stats_product.service_address2',
            'stats_product.service_city',
            'stats_product.service_state',
            'stats_product.service_zip',
            'stats_product.service_county',
            'stats_product.product_utility_name',
            'stats_product.rate_program_code',
            'stats_product.brand_name',
            'stats_product.confirmation_code',
            'stats_product.sales_agent_rep_id',
            'stats_product.result',
            'stats_product.disposition_label',
            'stats_product.tpv_agent_label',
            'stats_product.interaction_time'
        )->leftJoin(
            'stats_product_types',
            'stats_product.stats_product_type_id',
            'stats_product_types.id'
        )->where(
            'stats_product.brand_id',
            $brand->id
        )->whereDate(
            'stats_product.interaction_created_at',
            '>=',
            $start
        )->whereDate(
            'stats_product.interaction_created_at',
            '<=',
            $end
        )->whereNull(
            'stats_product.deleted_at'
        )->orderBy(
            'stats_product.confirmation_code'
        )->orderBy(
            'stats_product.interaction_created_at'
        )->get();

        return ["result" => "success", "message" => null, "data" => $records];
    }
}
