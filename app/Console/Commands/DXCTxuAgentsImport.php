<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Carbon\Carbon;

use App\Models\BrandUser;
use App\Models\BrandUserNote;
use App\Models\BrandUserOffice;
use App\Models\EmailAddress;
use App\Models\EmailAddressLookup;
use App\Models\Office;
use App\Models\PhoneNumber;
use App\Models\PhoneNumberLookup;
use App\Models\User;
use App\Models\Vendor;

class DXCTxuAgentsImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DXC:TXU:AgentsImport {--env=} {--filename=} {--office=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import TXU agents';

    /**
     * The brand identifier
     *
     * @var array
     */
    protected $brandId = [
        'stage' => 'cba28472-e721-4c21-9aca-dd7b2dfc06c9',
        'prod' => '200979d8-e0f5-41fb-8aed-e58a91292ca0'
    ];

    /**
     * Environment: 'prod' or 'stage'.
     *
     * @var string
     */
    protected $env = 'prod'; // prod env by default.

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
        if (!$this->validateArgs()) {
            return -1;
        }


        // Initial info display.
        $this->info("Env: " . $this->env);
        $this->info("");

        // Lookup office
        $this->info("Looking up office...");

        $office = Office::find($this->option('office'));
        if (!$office) {
            $this->info("  Unable to locate office '" . $this->option('office') . "'\n");
            $this->info("Exiting program.");

            return -1;
        }

        // Lookup vendor.
        // If vendor not found, do not exit. 
        // There is specific logic later in the program for handling that scenario. 
        // Not sure why it's done this way, but it's inline with the sales agents bulk import feature, so not messing with it.        
        $this->info("Looking up vendor...");

        $vendor = Vendor::find($office->vendor_id);


        // Display vendor and office and have user confirm
        $this->info("");
        $this->info("<fg=red>ATTENTION!</>");
        $this->info("<fg=red>----------</>");
        $this->info("Confirm that the office/vendor below is what you expected for the office ID you provided.\n");

        $this->info("Office: <fg=red>" . $office->name . "</> <fg=blue>(offices::id = " . $office->id . ")</>");
        $this->info("Vendor: <fg=red>" . (!$vendor ? "Not Found" : $vendor->vendor_label . "</> <fg=blue>(vendors::id = " . $vendor->id . ")</>"));

        if (!$this->confirm("Is this correct?")) {
            $this->info("   Information not confirmed.\n");
            $this->info("Exiting program.");

            return -1;
        }

        // Check if the specified file exists
        $filename = $this->option('filename');

        if (!file_exists($filename)) {
            $this->error("Unable to fine file: " . $filename);
            return -1;
        }

        $count = 0;
        $header = null;


        $resultCsv = [];

        // Parse and import data
        $file = fopen($filename, "r");
        while ($row = fgetcsv($file, 0)) {
            $count++;

            $this->info("\n-------------------------------------------------------");
            $this->info($count . ": ");

            // Grab header row
            if ($count == 1) {
                $this->info("HEADER ROW");
                $header = $row;
                continue;
            }

            // Combine header + row to get named indexes for this row
            $row = array_combine($header, $row);

            $this->info(trim($row['first_name']) . " " . trim($row['last_name']) . " (Rep ID: " . trim($row['tsr_id']) . ")\n");

            // Check if this agent already exists.
            $brandUserExists = BrandUser::select(
                'id'
            )->where(
                'tsr_id',
                trim($row['tsr_id'])
            )->where(
                'works_for_id',
                $this->brandId[$this->env]
            )->where(
                'employee_of_id',
                ($vendor
                    ? $vendor->vendor_id
                    : $office->vendor_id)
            )->withTrashed()->first();

            if ($brandUserExists) {
                $this->info("  A brand user with this Rep ID already exists. Skipping...");
                $row['import_result'] = "Skipped. A Record with this tsr_id already exists.";

                continue;
            }

            // Create User record
            $user = $this->createUser($row);

            // Create BrandUser record            
            $brandUser = $this->createBrandUser($row, $user, $office, $vendor);

            // Create BrandUserOffice record.
            // Junction table. Ties a BrandUser to an Office            
            $this->createBrandUserOffice($brandUser, $office);

            // Create PhoneNumber and/or PhoneNumberLookup records.
            if (isset($row['phone_number']) && trim($row['phone_number']) != "") {

                // Format phone number
                $phoneNum = '+1' . preg_replace(
                    '/[^0-9]/',
                    '',
                    trim($row['phone_number'])
                );

                // Lookup phone. This will either turn an existing record or create a new one.
                $phone = $this->addPhone($phoneNum);
                $this->addPhoneLookup($phone, $user);
            }

            // Create EmailAddress and/or EmailAddressLookup records.
            if (isset($row['email_address']) && trim($row['email_address']) != "") {
                $email = $this->addEmail(trim($row['email_address']));
                $this->addEmailLookup($email, $user);
            }

            // Create BrandUserNotes records.
            $this->addNotes($row['tsr_notes'], $brandUser);

            $row['import_result'] = "Success";
            $resultCsv[] = $row;
        }

        fclose($file);

        // Write results
        $fileInfo = pathinfo($this->option('filename'));
        $resultFile = $fileInfo['dirname'] . "/" . $fileInfo['filename'] . " - Results." . $fileInfo['extension'];

        $file = fopen($resultFile, "w");
        $header[] = "import_result";

        fputcsv($file, $header);

        foreach ($resultCsv as $row) {
            fputcsv($file, $row);
        }

        fclose($file);
    }

    /**
     * Validates commandline args.
     */
    private function validateArgs()
    {
        $this->info("Validating args...");

        if (!$this->option('filename')) {
            $this->error("param --file= is required");
            return false;
        }

        if (!$this->option('filename')) {
            $this->error("param --office= is required");
            return false;
        }

        // Validate env.
        if ($this->option('env')) {
            if (
                strtolower($this->option('env')) == 'prod' ||
                strtolower($this->option('env')) == 'stage'
            ) {
                $this->env = strtolower($this->option('env'));
            } else {
                $this->error('Invalid --env value: ' . $this->option('env'));
                return false;
            }
        }

        return true;
    }

    /**
     * Adds BrandUserNote reocrds.
     * 
     * @param string $notes - JSON string containing the notes to add
     */
    private function addNotes($notes, $brandUser)
    {
        $this->info("  Adding BrandUserNote records...");

        if (trim($notes) == "") {
            $this->info("    No notes found. Skipping.");
            return;
        }

        // Break out notes. We're going to ignore empty array entries later (Empty entries are a side-effect of how notes work in DXC).
        $notes = json_decode($notes);

        $submitter = ""; // TODO: Set ID. This identifies the person adding the note.
        if ($this->env == "stage") {
            $submitter = "bc99747e-03a2-4287-a317-1d1858d93676";
        } else {
            $submitter = "66d5cd52-a472-425c-98aa-0cc7f3e4796c";
        }

        print_r($notes);

        // Add notes. Skip empty notes
        foreach ($notes as $note) {

            $this->info("    - Processing: " . json_encode($note));

            //if (!isset($note['body']) || !isset($note['date']) || !isset($note['user'])) {
            if (!isset($note->body) || $note->body == "") {
                $this->info("      Skipping. Note property 'body' is missing or empty.");

                continue;
            }

            $noteStr = $note->date . " -- " . $note->user . " -- " . $note->body;

            $brandUserNote = new BrandUserNote();
            $brandUserNote->brand_user_id = $brandUser->id;
            $brandUserNote->added_by_brand_user_id = $submitter;
            $brandUserNote->note = $noteStr;

            if (!$this->option('dry-run')) {
                $brandUserNote->save();
                $this->info("      Created BrandUserNote record.");
            } else {
                $this->info("      Dry run. BrandUserNote record not saved.");
            }
        }
    }

    /**
     * Checks if an EmailAddress record exists. Returns either the existing record, or a newly created record.
     * 
     * @param string $emailAddr - The email address to add.
     */
    private function addEmail($emailAddr)
    {
        $this->info("  Checking for existing EmailAddress record: '" . $emailAddr . "'...");

        // Does it already exist?
        $email = EmailAddress::where('email_address', $emailAddr)
            ->withTrashed()
            ->first();

        // Create it, if it doesn't exist.
        $newEmail = null;

        if (!$email) {
            $newEmail = new EmailAddress();
            $newEmail->email_address = $emailAddr;

            if (!$this->option('dry-run')) {
                $newEmail->save();

                $this->info("    Not found. Created a new EmailAddress record");
            } else {
                $this->info("    Not found. Dry run. New EmailAddress record not saved");
            }
        } else {
            $this->info("    Found! We'll use the existing record <fg=blue>(" . $email->id . ")</>");
        }

        return ($email ? $email : $newEmail);
    }

    /**
     * Checks if an EmailAddressLookup record exists. Returns either the existing record, or a newly created record.
     * 
     * @param EmailAddress $email - The EmailAddress record.
     * @param User $user - The user record.
     */
    private function addEmailLookup($email, $user)
    {
        $this->info("  Checking for existing EmailAddressLookup record...");

        // Does it already exist?
        $emailLookup = EmailAddressLookup::where('type_id', $user->id)
            ->where('email_address_type_id', 1) // email belongs to a User record
            ->where('email_address_id', $email->id)
            ->withTrashed()
            ->first();

        // Create it, if it doesn't exist.
        if (!$emailLookup) {
            $newEmailLookup = new EmailAddressLookup();
            $newEmailLookup->email_address_type_id = 1;
            $newEmailLookup->type_id = $user->id;
            $newEmailLookup->email_address_id = $email->id;

            if (!$this->option('dry-run')) {
                $newEmailLookup->save();

                $this->info("    Not found. Created a new EmailAddressLookup record");
            } else {
                $this->info("    Not found. Dry run. New EmailAddressLookup record not saved");
            }
        } else {
            if (!$this->option('dry-run')) {
                $emailLookup->restore();
                $this->info("    Found! We'll use the existing (or if trashed, restored) EmailAddressLookup record <fg=blue>(" . $emailLookup->id . ")</>");
            } else {
                $this->info("    Found! Dry run. EmailAddressLookup record not restored");
            }
        }
    }

    /**
     * Checks if an PhoneNumber record exists. Returns either the existing record, or a newly created record.
     * 
     * @param string $phoneNum - The phone number to add.
     */
    private function addPhone($phoneNum)
    {
        $this->info("  Checking for existing PhoneNumber record: " . $phoneNum . "...");

        // Does it already exist?
        $phone = PhoneNumber::where('phone_number', $phoneNum)
            ->withTrashed()
            ->first();

        // Create it, if it doesn't exist.
        $newPhone = null;

        if (!$phone) {
            $newPhone = new PhoneNumber();
            $newPhone->phone_number = $phoneNum;

            if (!$this->option('dry-run')) {
                $newPhone->save();

                $this->info("    Not found. Created a new PhoneNumber record");
            } else {
                $this->info("    Not found. Dry run. New PhoneNumber record not saved");
            }
        } else {
            $this->info("    Found! We'll use the existing record <fg=blue>(" . $phone->id . ")</>");
        }

        return ($phone ? $phone : $newPhone);
    }

    /**
     * Checks to see if an existing PhoneNumberLookup record exists for a given User and PhoneNumber.
     * Returns the existing record, or creates and returns a new record.
     * 
     * @param PhoneNumber $phone - The PhoneNumber record.
     * @param User $user - The User record.
     */
    private function addPhoneLookup($phone, $user)
    {
        $this->info("  Checking for existing PhoneNumberLookup record...");

        // Does it already exist?
        $phoneLookup = PhoneNumberLookup::where('type_id', $user->id)
            ->where('phone_number_type_id', 1) // phone number belongs to a User record
            ->where('phone_number_id', $phone->id)
            ->withTrashed()
            ->first();

        // Create it, if it doesn't exist.
        $newPhoneLookup = null;

        if (!$phoneLookup) {
            $newPhoneLookup = new PhoneNumberLookup();
            $newPhoneLookup->phone_number_type_id = 1;
            $newPhoneLookup->type_id = $user->id;
            $newPhoneLookup->phone_number_id = $phone->id;

            if (!$this->option('dry-run')) {
                $newPhoneLookup->save();

                $this->info("    Not found. Creating a new PhoneNumberLookup record");
            } else {
                $this->info("    Not found. Dry run. New PhoneNumberLookup record not saved");
            }
        } else {
            if (!$this->option('dry-run')) {
                $phoneLookup->restore();
                $this->info("    Found! We'll use the existing (or if trashed, restored) PhoneNumberLookup record <fg=blue>(" . $phoneLookup->id . ")</>");
            } else {
                $this->info("    Found! Dry run. PhoneNumberLookup record not restored");
            }
        }

        return ($phoneLookup ? $phoneLookup : $newPhoneLookup);
    }

    /**
     * Creates the BrandUserOffice record.
     * 
     * @param BrandUser $brandUser - The BrandUser record.
     * @param Office $office - The Office record.
     */
    private function createBrandUserOffice($brandUser, $office)
    {
        $this->info("  Creating BrandUserOffice record...");

        $brand_user_office = new BrandUserOffice();
        $brand_user_office->brand_user_id = $brandUser->id;
        $brand_user_office->office_id = $office->id;

        if (!$this->option('dry-run')) {
            $brand_user_office->save();
        } else {
            $this->info("    Dry run. BrandUserOffice record not saved");
        }

        return $brand_user_office;
    }

    /**
     * Creates the User record.
     * 
     * @param array $row - Current CSV row being processed.
     */
    private function createUser($row)
    {
        $this->info("  Creating User record...");

        $user = new User();
        $user->first_name = trim($row['first_name']);
        $user->last_name = trim($row['last_name']);
        $user->username = trim($row['user_name']);
        $user->password_change_required = 1;
        $user->password = bcrypt(trim($row['password']));

        if (!$this->option('dry-run')) {
            $user->save();
        } else {
            $this->info("    Dry run. User record not saved");
        }

        return $user;
    }

    /**
     * Creates the BrandUser record.
     * 
     * @param array $row - Current CSV row being processed.
     * @param User $user - The User record.
     * @param Office $office - The Office record.
     * @param Vendor $vendor - The Vendor record.
     */
    private function createBrandUser($row, $user, $office, $vendor)
    {
        $this->info("  Creating BrandUser record...");

        $brand_user = new BrandUser();
        $brand_user->works_for_id = $office->brand_id;

        // If the vendor record was found, use vendors->vendor_id. This will point to the vendor's record in the Brands table.
        // If the vendor reocrd was NOT found, use office->vendor_id. This will point to the vendors record in the Vendors table.
        if ($vendor) {
            $brand_user->employee_of_id = $vendor->vendor_id;
        } else {
            $brand_user->employee_of_id = $office->vendor_id;
        }

        $brand_user->user_id = $user->id;
        $brand_user->tsr_id = trim($row['tsr_id']);
        $brand_user->role_id = 3;

        $brand_user->pass_bg_chk = (isset($row['passed_background_check']))
            ? $this->resolveBoolean($row['passed_background_check'])
            : false;

        $brand_user->pass_drug_test = (isset($row['passed_drug_test']))
            ? $this->resolveBoolean($row['passed_drug_test'])
            : false;

        $brand_user->pass_exam = (isset($row['passed_exam']))
            ? $this->resolveBoolean($row['passed_exam'])
            : false;

        $brand_user->ssn = (isset($row['ssn4']))
            ? trim($row['ssn4'])
            : null;

        $brand_user->govt_id = (isset($row['govt_id']))
            ? trim($row['govt_id'])
            : null;

        $brand_user->shirt_size = (isset($row['shirt_size']))
            ? trim($row['shirt_size'])
            : null;

        // Parse gender
        $gender = null;
        if (isset($row['gender'])) {
            switch (trim($row['gender'])) {
                case 'Male':
                case 'M':
                    $gender = 1;
                    break;

                case 'Female':
                case 'F':
                    $gender = 2;
                    break;
            }
        }

        $brand_user->gender_id = $gender;

        // Parse language
        // Start off by defaulting to English/Spanish
        $language = 3;
        if (isset($row['tsr_language'])) {
            switch (strtolower(trim($row['tsr_language']))) {
                case 'english':
                    $language = 1;
                    break;

                case 'spanish':
                    $language = 2;
                    break;

                case 'both':
                default:
                    $language = 3;
            }
        }

        $brand_user->language_id = $language;

        // Set as inactive?
        if (isset($row['active']) && trim($row['active']) == ".F.") {
            $brand_user->status = 0;
            $brand_user->deleted_at = Carbon::now("America/Chicago");
        }

        if (!$this->option('dry-run')) {
            $brand_user->save();
        } else {
            $this->info("    Dry run. BrandUser record not saved");
        }

        return $brand_user;
    }

    /**
     * Resolves string value to a boolean.
     */
    private function resolveBoolean($text)
    {
        if (!isset($text)) {
            return false;
        }

        $itext = trim(mb_strtolower($text));
        switch ($itext) {
            case 'true':
            case 'yes':
            case 'y':
            case 't':
            case '1':
            case '.F.':
            case '.f.':
                return true;
            default:
                return false;
        }
    }
}
