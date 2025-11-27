<?php

namespace App\Console\Commands;

use App\Models\BgchkPayload;
use App\Models\UserHireflowActivity;

use Illuminate\Support\Facades\Log;

use Illuminate\Console\Command;

class TestShieldScreening extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:shieldscreening';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a test background screen without HRTPV';

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
     * Shield Screening
     *
     * @param array $data - data array
     * 
     * @return void
     */
    public function shieldScreening($data)
    {
        $url = (config('app.env') == 'production')
            ? config('app.urls.mgmt')
            : 'https://mgmt.staging.tpvhub.com';
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <BackgroundCheck userId="' . getenv("TAZ_API_USER") . '" password="' . getenv("TAZ_API_PASSWORD") . '">
           <BackgroundSearchPackage action="submit" type="' . $data['bgchk_package'] . '">
              <Organization type="x:requesting">
                 <OrganizationName>' . $data['brand_name'] . '</OrganizationName>
              </Organization>
              <ReferenceId>' . $data['user_id'] . '</ReferenceId>
              <PersonalData>
                 <PersonName>
                    <GivenName>' . $data['first_name'] . '</GivenName>
                    <MiddleName>' . $data['middle_name'] . '</MiddleName>
                    <FamilyName>' . $data['last_name'] . '</FamilyName>
                 </PersonName>
                 <Aliases>
                    <PersonName>
                        <GivenName>' . $data['first_name'] . '</GivenName>
                        <MiddleName>' . $data['middle_name'] . '</MiddleName>
                        <FamilyName>' . $data['last_name'] . '</FamilyName>
                    </PersonName>
                 </Aliases>
                 <DemographicDetail>
                    <GovernmentId issuingAuthority="SSN">' . $data['ssn'] . '</GovernmentId>
                    <DateOfBirth>' . $data['dob'] . '</DateOfBirth>
                 </DemographicDetail>
                 <PostalAddress>
                    <PostalCode>' . $data['zip'] . '</PostalCode>
                    <Region>' . $data['state'] . '</Region>
                    <Municipality>' . $data['city'] . '</Municipality>
                    <DeliveryAddress>
                       <AddressLine>' . $data['address'] . '</AddressLine>
                    </DeliveryAddress>
                 </PostalAddress>
                 <EmailAddress></EmailAddress>
              </PersonalData>
                <Screenings>
                    <Screening type="criminal" qualifier="national_alias" />
                    <Screening type="sex_offender" />
                    <Screening type="executivesummary" />
                    <AdditionalItems type="x:postback_url">
                        <Text>' . $url . '/api/webhook/shieldscreening</Text>
                    </AdditionalItems>
                    <AdditionalItems type="x:embed_credentials">
                        <Text>TRUE</Text>
                    </AdditionalItems>
                </Screenings>
           </BackgroundSearchPackage>
        </BackgroundCheck>';

        $bgck = new BgchkPayload;
        $bgck->brand_id = $data['brand_id'];
        $bgck->user_id = $data['user_id'];
        $bgck->direction = "out";
        $bgck->payload = $xml;
        $bgck->save();

        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $dom->formatOutput = true;

        $ch = curl_init();

        $build = array('request' => trim($dom->saveXML()));

        curl_setopt($ch, CURLOPT_URL, getenv('TAZ_API_ENDPOINT'));
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($build));

        $result = curl_exec($ch);

        Log::debug($result);

        $bgck = new BgchkPayload;
        $bgck->brand_id = $data['brand_id'];
        $bgck->user_id = $data['user_id'];
        $bgck->direction = "in";
        $bgck->payload = $result;
        $bgck->save();

        curl_close($ch);

        $xml = simplexml_load_string($result, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $array = json_decode($json, true);

        Log::debug($array);

        if (isset($array['BackgroundReportPackage'])
            && isset($array['BackgroundReportPackage']['OrderId'])) {
            Log::debug("ORDER ID = " . $array['BackgroundReportPackage']['OrderId']);

            $providerId = $array['BackgroundReportPackage']['OrderId'];

            $uha = UserHireflowActivity::find($data['user_hireflow_activity_id']);
            $uha->screen_id = $providerId;
            $uha->save();
        } else {
            echo "There was an error processing your background check.";
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $data = [
            'bgchk_package' => 'ShieldPremium + Credit',
            'brand_id' => '04B0F894-172C-470F-813B-4F58DBD35BAE',
            'brand_name' => 'Forward Thinking Energy',
            'user_id' => 'a74cf9af-f484-404c-9a5e-b1017bb2d5b7',
            'first_name' => 'HRTPV',
            'middle_name' => '',
            'last_name' => 'Test1',
            'ssn' => '111223333',
            'dob' => '01/02/1980',
            'zip' => '74955',
            'state' => 'OK',
            'city' => 'Sallisaw',
            'address' => '123 Main St.',
        ];

        $this->shieldScreening($data);
    }
}
