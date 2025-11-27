<?php

namespace App\Console\Commands;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Config;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Upload;
use App\Models\StatsProduct;
use App\Models\LogEnrollmentFile;
use App\Models\Interaction;
use App\Models\BrandEnrollmentFile;
use App\Models\State;

class CreateEnrollmentFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:enrollment
        {--brand=*}
        {--force}
        {--spt=}
        {--noalert}
        {--email=*}
        {--date=}
        {--date2=}
        {--keepfile}
        {--confirmation_code=}
        {--debug_data}
        {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create enrollment files';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Enrollment File FTP Upload.
     *
     * @param array  $config - configuration needed to perform FTP upload
     * @param string $file   - path to file being uploaded
     *
     * @return string - Status message
     */
    public function ftpUpload($config, $file)
    {
        $status = 'FTP at ' . Carbon::now() . '. Status: ';
        try {
            $adapter = new Ftp(
                [
                    'host' => $config['hostname'],
                    'username' => $config['username'],
                    'password' => $config['password'],
                    'port' => (isset($config['port'])) ? $config['port'] : 21,
                    'root' => (isset($config['root'])) ? $config['root'] : '/',
                    'passive' => (isset($config['passive'])) ? $config['passive'] : true,
                    'ssl' => (isset($config['ssl'])) ? $config['ssl'] : false,
                    'timeout' => 30,
                ]
            );

            $filesystem = new Filesystem(
                $adapter,
                new Config(
                    [
                        'disable_asserts' => true,
                    ]
                )
            );

            $stream = fopen(public_path('tmp/' . $file), 'r+');
            $filesystem->writeStream(
                $file,
                $stream
            );

            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Exception $e) {
            $status .= 'Error! The reason reported is: ' . $e;

            return $status;
        }
        $status .= 'Success!';

        return $status;
    }

    /**
     * Enrollment File SFTP Upload.
     *
     * @param array  $config - configuration needed to perform SFTP upload
     * @param string $file   - path to file being uploaded
     *
     * @return string - Status message
     */
    public function sftpUpload($config, $file)
    {
        $status = 'SFTP at ' . Carbon::now() . '. Status: ';
        try {
            $adapter = new SftpAdapter(
                [
                    'host' => $config['hostname'],
                    'port' => (isset($config['port'])) ? $config['port'] : 22,
                    'username' => $config['username'],
                    'password' => $config['password'],
                    //'privateKey' => 'path/to/or/contents/of/privatekey',
                    'root' => (isset($config['root'])) ? $config['root'] : '/',
                    'timeout' => 10,
                    'directoryPerm' => 0755,
                ]
            );
            $filesystem = new Filesystem($adapter);
            $stream = fopen(public_path('tmp/' . $file), 'r+');
            $filesystem->writeStream(
                $file,
                $stream
            );

            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Exception $e) {
            $status .= 'Error! The reason reported is: ' . $e;

            return $status;
        }
        $status .= 'Success!';

        return $status;
    }

    /**
     * Enrollment File Email.
     *
     * @param array  $config - configuration needed to perform email
     * @param string $file   - path to file being uploaded
     *
     * @return string - Status message
     */
    public function emailUpload($config, $file)
    {
        $email_address = [];
        $uploadStatus = [];
        if ($this->option('email')) {
            $email_address[] = $this->option('email')[0];
        } else {
            if (!is_array($config['email_address'])) {
                $email_address[] = $config['email_address'];
            } else {
                $email_address = $config['email_address'];
            }
        }

        if ('production' != config('app.env')) {
            $subject = 'Enrollment File (' . config('app.env') . ') '
                . Carbon::now();
        } else {
            $subject = 'Enrollment File '
                . Carbon::now();
        }

        $data = [
            'timestamp' => Carbon::now(),
        ];

        $attach = public_path('tmp/' . $file);

        for ($i = 0; $i < count($email_address); ++$i) {
            $status = 'Email to ' . $email_address[$i]
                . ' at ' . Carbon::now() . '. Status: ';

            try {
                Mail::send(
                    'emails.sendEnrollmentFileToClient',
                    $data,
                    function ($message) use ($subject, $email_address, $i, $attach) {
                        $message->subject($subject);
                        $message->from('no-reply@tpvhub.com');
                        $message->to(trim($email_address[$i]));
                        $message->attach($attach);
                    }
                );
            } catch (\Exception $e) {
                $status .= 'Error! The reason reported is: ' . $e;
                $uploadStatus[] = $status;
            }

            $status .= 'Success!';
            $uploadStatus[] = $status;
        }

        return $uploadStatus;
    }

    /**
     * Get Brands with Enrollment File configuration.
     *
     * @return object
     */
    public function getBrands()
    {
        $brands = BrandEnrollmentFile::select(
            'brand_enrollment_files.id as bef_id',
            'brands.id AS brand_id',
            'brands.name'
        )->join(
            'brands',
            'brand_enrollment_files.brand_id',
            'brands.id'
        )->where(
            'brand_enrollment_files.live_enroll',
            0
        );

        if (!$this->option('force')) {
            $brands = $brands->where(
                'next_run',
                '<=',
                Carbon::now()
            );
        }

        if ($this->option('brand')) {
            $brands = $brands->where(
                'brands.id',
                $this->option('brand')
            );
        }

        $brands = $brands->orderBy(
            'brands.name'
        )->get();

        return $brands;
    }

    /**
     * Get Report Fields.
     *
     * Fields used to design the enrollment file structure/columns
     *
     * @param string $id - guid for brand_enrollment_files.id
     *
     * @return array
     */
    public function getReportFields(string $bef_id)
    {
        $config = BrandEnrollmentFile::select(
            'report_fields'
        )->find($bef_id);
        if ($config) {
            return json_decode($config->report_fields, true);
        }

        return false;
    }

    /**
     * Get Delivery Data.
     *
     * Data used for the formatting and scheduling.
     *
     * @param string $id - guid for brand_enrollment_files.id
     *
     * @return array
     */
    public function getDeliveryData(string $id)
    {
        $dd = BrandEnrollmentFile::select(
            'delivery_data'
        )->where(
            'id',
            $id
        )->first();

        return json_decode($dd->delivery_data, true);
    }

    /**
     * Get Run History.
     *
     * @param string $id - guid for brand_enrollment_files.id
     *
     * @return array
     */
    public function getRunHistory(string $id)
    {
        $rh = BrandEnrollmentFile::select(
            'run_history'
        )->where(
            'id',
            $id
        )->first();

        return json_decode($rh->run_history, true);
    }

    /**
     * Get Next Run Date.
     *
     * @param array $config - Configuration used for run dates
     *
     * @return string
     */
    public function getNextRun(array $config)
    {
        $time = explode(':', $config['run_time']);
        $next_run = Carbon::now(
            'America/Chicago'
        )->hour(
            $time[0]
        )->minute(
            $time[1]
        )->second(
            '00'
        )->addDay()->setTimeZone('UTC');

        return $next_run;
    }

    /**
     * Write string data to CSV file.
     *
     * @param array  $data      - Data to be written
     * @param string $delimiter - Delimiter string
     *
     * @return string
     */
    public function strPutcsv(array $data, string $delimiter, $enclosure = false)
    {
        $fh = fopen('php://temp', 'rw');

        if (current($data) && array_keys(current($data))) {
            fputcsv($fh, array_keys(current($data)), $delimiter);
        }

        foreach ($data as $row) {
            if (!$enclosure) {
                fputcsv($fh, $row, $delimiter);
            } else {
                fputcsv($fh, $row, $delimiter, chr(0));
            }
        }

        rewind($fh);
        $prepared = stream_get_contents($fh);
        fclose($fh);

        return $prepared;
    }

    public function formatConditional($products, $i, $field, $type, $format = null, $add_term = false)
    {
        if (isset($products[$i][$field])) {
            if ('date' === $type) {
                $format = (isset($format))
                    ? $format : 'Y-m-d';
                $the_date = Carbon::parse(
                    $products[$i][$field]
                );

                if ($add_term) {
                    $term = $products[$i]['product_term'];
                    $term_type = $products[$i]['product_term_type'];

                    switch ($term_type) {
                        case 'day':
                            $the_date = $the_date
                                ->addDays($term);
                            break;
                        case 'week':
                            $the_date = $the_date
                                ->addWeeks($term);
                            break;
                        case 'month':
                            $the_date = $the_date
                                ->addMonths($term);
                            break;
                        case 'year':
                            $the_date = $the_date
                                ->addYear($term);
                            break;
                    }
                }

                return $the_date->format($format);
            } else {
                return $products[$i][$field];
            }
        } elseif ($field === 'auth_full_name') {
            return mb_strtoupper(
                $products[$i]['auth_first_name']
                    . ' ' . $products[$i]['auth_last_name']
            );
        } elseif ($field === 'bill_full_name') {
            return mb_strtoupper(
                $products[$i]['bill_first_name']
                    . ' ' . $products[$i]['bill_last_name']
            );
        } elseif ($field === 'auth_full_name_middle') {                       
            return mb_strtoupper(
                $products[$i]['auth_first_name']
                . ' ' . $products[$i]['auth_middle_name']
                . ' ' . $products[$i]['auth_last_name']
            );
        } elseif ($field === 'bill_full_name_middle') {                       
            return mb_strtoupper(
                $products[$i]['bill_first_name']
                . ' ' . $products[$i]['bill_middle_name']
                . ' ' . $products[$i]['bill_last_name']
            );
        } elseif ($field === 'billing_full_address') {
            return mb_strtoupper(
                $products[$i]['billing_address1']
                . ' ' . $products[$i]['billing_address2']
            );
        } elseif ($field === 'service_full_address') {
            return mb_strtoupper(
                $products[$i]['service_address1']
                . ' ' . $products[$i]['service_address2']
            );
        }

        return $field;
    }

    /**
     * Get enrollment data.
     *
     * @param object $brand  - Brand object
     * @param array  $fields - Enrollment file structure
     * @param string $date   - Created Date
     *
     * @return array
     */
    public function getData(
        BrandEnrollmentFile $brand,
        array $fields,
        string $date,
        $date2
    ) {
        $products = StatsProduct::select(
            'stats_product.*',
            'utilities.duns',
            'leads.external_lead_id'
        )->where(
            'stats_product.brand_id',
            $brand->brand_id
        )->leftJoin(
            'utilities',
            'stats_product.utility_id',
            'utilities.id'
        )->leftJoin(
            'leads',
            'stats_product.lead_id',
            'leads.id'
        )->leftJoin(
            'events',
            'stats_product.event_id',
            'events.id'
        )->where(
            'stats_product.result',
            'Sale'
        );

        if ($date2) {
            $products = $products->whereBetween(
                'stats_product.interaction_created_at',
                [
                    $date,
                    $date2,
                ]
            );
        } else {
            $products = $products->whereDate(
                'stats_product.interaction_created_at',
                $date
            );
        }

        $products = $products->where(
            'events.event_category_id',
            '!=',
            3
        )->orderBy(
            'stats_product.interaction_created_at',
            'desc'
        );

        if ($this->option('spt')) {
            $products = $products->where(
                'stats_product.stats_product_type_id',
                $this->option('spt')
            );
        } else {
            $products = $products->where(
                'stats_product.stats_product_type_id',
                1
            );
        }

        if ($this->option('confirmation_code')) {
            $products->where(
                'stats_product.confirmation_code',
                $this->option('confirmation_code')
            );
        }

        $products = $products->with(
            [
                'rate',
                'rate.utility',
                'rate.utility.utility',
                'rate.utility.utility.brand_identifier' => function ($query) use ($brand) {
                    $query->where(
                        'brand_id',
                        $brand->brand_id
                    );
                },
                'product.promotion',
            ]
        )->get();

        if ($this->option('debug_data')) {
            print_r($products->toArray());
            exit();
        }

        $enrollment = [];
        if (!empty($products)) {
            for ($i = 0; $i < count($products); ++$i) {
                $row = [];
                foreach ($fields as $field) {
                    switch ($field['field']) {
                        case 'parse':
                            if (
                                isset($field['parse_field'])
                                && isset($products[$i][$field['parse_field']])
                            ) {
                                $parse_field = $products[$i][$field['parse_field']];
                                $parse_regex = $field['parse_regex'];

                                if (preg_match('/' . $parse_regex . '/', $parse_field)) {
                                    $row[$field['header']] = $field['parse_success'];
                                } else {
                                    $row[$field['header']] = $field['parse_failure'];
                                }
                            } else {
                                $row[$field['header']] = $field['parse_failure'];
                            }

                            break;
                        case 'static':
                            if (
                                isset($field['htmlentities'])
                                && $field['htmlentities']
                            ) {
                                $row[$field['header']]
                                    = htmlspecialchars(
                                        $field['value'],
                                        ENT_QUOTES
                                    );
                            } else {
                                $row[$field['header']] = $field['value'];
                            }

                            break;
                        case 'blank':
                            $row[$field['header']] = '';
                            break;
                        case 'sales_agent_name':
                            $row[$field['header']] = mb_strtoupper(
                                $products[$i]['sales_agent_name']
                            );
                            break;
                        case 'auth_full_name':
                            $row[$field['header']] = mb_strtoupper(
                                $products[$i]['auth_first_name']
                                    . ' ' . $products[$i]['auth_last_name']
                            );
                            break;
                        case 'auth_full_name_middle':
                            $row[$field['header']] = mb_strtoupper(
                                $products[$i]['auth_first_name']
                                . ' ' . $products[$i]['auth_middle_name']
                                . ' ' . $products[$i]['auth_last_name']
                            );
                            break;
                        case 'call_count':
                            $is = Interaction::where(
                                'event_id',
                                $products[$i]['event_id']
                            )->orderBy(
                                'created_at',
                                'asc'
                            )->pluck('id')->toArray();

                            $position = array_search($products[$i]['interaction_id'], $is);
                            $row[$field['header']] = $position - 1;
                            break;

                        case 'company_name':
                            $row[$field['header']] = mb_strtoupper($products[$i]['company_name']);
                            break;

                        case 'bill_full_name':
                            $row[$field['header']] = mb_strtoupper(
                                $products[$i]['bill_first_name']
                                    . ' ' . $products[$i]['bill_last_name']
                            );
                            break;
                        case  'bill_full_name_middle':                      
                            $row[$field['header']] = mb_strtoupper(
                                $products[$i]['bill_first_name']
                                . ' ' . $products[$i]['bill_middle_name']
                                . ' ' . $products[$i]['bill_last_name']
                            );
                            break;
                        case 'billing_full_address':
                            $row[$field['header']] = mb_strtoupper(
                                $products[$i]['billing_address1']
                                . ' ' . $products[$i]['billing_address2']
                            );
                            break;
                        case 'service_full_address':
                            $row[$field['header']] = mb_strtoupper(
                                $products[$i]['service_address1']
                                . ' ' . $products[$i]['service_address2']
                            );
                            break;
                        case 'promo_code':
                            $row[$field['header']] = (isset($products[$i]['product'])
                                && isset($products[$i]['product']['promotion']))
                                ? $products[$i]['product']['promotion']['promotion_code']
                                : null;
                            break;
                        
                        case 'spelled_state':
                            $row[$field['header']] = State::where('state_abbrev', $products[$i]['billing_state'])->first()->name;
                            break;

                        case 'contract_end_date':
                            
                            $contract_end_date = Carbon::parse($products[$i]['created_at']);
                            $term = $products[$i]['product_term'];
                            $intro_term = $products[$i]['product_intro_term'];
                            $term_type = $products[$i]['product_term_type'];
                            $rate_type = $products[$i]['product_rate_type'];

                            $addtionalDate = 0;
                            if($rate_type == 'fixed'){
                                $addtionalDate = $term;
                            }elseif($rate_type == 'tiered'){
                                $addtionalDate = $term + $intro_term;
                            }elseif($rate_type == 'variable'){
                                $addtionalDate  = 1;
                                $term_type = 'month';
                            }
        
                            switch ($term_type) {
                                case 'day':
                                    $contract_end_date = $contract_end_date
                                        ->addDays($addtionalDate);
                                    break;
                                case 'week':
                                    $contract_end_date = $contract_end_date
                                        ->addWeeks($addtionalDate);
                                    break;
                                case 'month':
                                    $contract_end_date = $contract_end_date
                                        ->addMonths($addtionalDate);
                                    break;
                                case 'year':
                                    $contract_end_date = $contract_end_date
                                        ->addYear($addtionalDate);
                                    break;
                            } ;

                            $row[$field['header']] = $contract_end_date->format('m/d/Y');
                            break;

                        case 'conditional':
                            $case = $field['header']['case'];
                            $when = $field['header']['when'];
                            $then = $field['header']['then'];
                            $when2 = @$field['header']['when2'];
                            $then2 = @$field['header']['then2'];
                            $when3 = @$field['header']['when3'];
                            $then3 = @$field['header']['then3'];
                            $when4 = @$field['header']['when4'];
                            $then4 = @$field['header']['then4'];
                            $when5 = @$field['header']['when5'];
                            $then5 = @$field['header']['then5'];
                            $when6 = @$field['header']['when6'];
                            $then6 = @$field['header']['then6'];
                            $when7 = @$field['header']['when7'];
                            $then7 = @$field['header']['then7'];
                            $when8 = @$field['header']['when8'];
                            $then8 = @$field['header']['then8'];
                            $when9 = @$field['header']['when9'];
                            $then9 = @$field['header']['then9'];
                            $else = $field['header']['else'];
                            $as = $field['header']['as'];
                            $type = @$field['header']['type'];
                            $format = @$field['header']['format'];
                            $add_term = @$field['header']['add_term'];

                            if (strpos($field['header']['case'], '.') !== false) {
                                $sp = explode('.', $field['header']['case']);
                                $pvalue = $products[$i][$sp[0]][$sp[1]];
                            } else {
                                $pvalue = $products[$i][$case];
                            }

                            if ($pvalue == $when) {
                                if ('identifier' == $then) {
                                    $data = $products[$i]['identifiers'][0]['identifier'];
                                } else {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $then,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                }
                            } else {
                                if (
                                    isset($when2)
                                    && strlen(trim($when2)) > 0
                                    && $pvalue == $when2
                                ) {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $then2,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                } elseif (
                                    isset($when3)
                                    && strlen(trim($when3)) > 0
                                    && $pvalue == $when3
                                ) {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $then3,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                } elseif (
                                    isset($when4)
                                    && strlen(trim($when4)) > 0
                                    && $pvalue == $when4
                                ) {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $then4,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                } elseif (
                                    isset($when5)
                                    && strlen(trim($when5)) > 0
                                    && $pvalue == $when5
                                ) {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $then5,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                } elseif (
                                    isset($when6)
                                    && strlen(trim($when6)) > 0
                                    && $pvalue == $when6
                                ) {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $then6,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                } elseif (
                                    isset($when7)
                                    && strlen(trim($when7)) > 0
                                    && $pvalue == $when7
                                ) {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $then7,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                } elseif (
                                    isset($when8)
                                    && strlen(trim($when8)) > 0
                                    && $pvalue == $when8
                                ) {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $then8,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                } elseif (
                                    isset($when9)
                                    && strlen(trim($when9)) > 0
                                    && $pvalue == $when9
                                ) {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $then9,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                } else {
                                    $data = $this->formatConditional(
                                        $products,
                                        $i,
                                        $else,
                                        $type,
                                        $format,
                                        $add_term
                                    );
                                }
                            }

                            $row[$as] = $data;
                            break;
                        default:
                            switch ($field['field']) {
                                case 'custom_fields':
                                    $row[$field['header']] = '';
                                    $custom_fields = json_decode($products[$i]['custom_fields'], true);

                                    if (isset($custom_fields) && count($custom_fields) > 0) {
                                        foreach ($custom_fields as $cf) {
                                            if ($field['custom_field'] == $cf['output_name']) {
                                                if (
                                                    isset($field['type'])
                                                    && 'date' == $field['type']
                                                ) {
                                                    $format = (isset($field['format']))
                                                        ? $field['format'] : 'Y-m-d';

                                                    $the_date = Carbon::parse(
                                                        $cf['value']
                                                    )->format($format);

                                                    $row[$field['header']] = $the_date;
                                                } else {
                                                    $row[$field['header']] = $cf['value'];
                                                }
                                            }
                                        }
                                    }

                                    break;
                                case 'utility_commodity_external_id':
                                    if (
                                        isset($products[$i]['utility_commodity_external_id'])
                                        && strlen(trim($products[$i]['utility_commodity_external_id'])) > 0
                                    ) {
                                        $row[$field['header']] = $products[$i]['utility_commodity_external_id'];
                                    } else {
                                        $row[$field['header']] = $products[$i]['product_utility_external_id'];
                                    }

                                    break;
                                case 'recording':
                                    if (isset($field['with_cdn']) && $field['with_cdn']) {
                                        if (strlen(trim($products[$i]['recording'])) > 0) {
                                            $row[$field['header']] = env(
                                                'AWS_CLOUDFRONT'
                                            ) . '/' . $products[$i]['recording'];
                                        } else {
                                            $row[$field['header']] = $products[$i]['recording'];
                                        }
                                    } elseif (isset($field['file_only']) && $field['file_only']) {
                                        $parts = explode('/', $products[$i]['recording']);
                                        $row[$field['header']] = $parts[count($parts) - 1];
                                    } else {
                                        $row[$field['header']] = $products[$i]['recording'];
                                    }

                                    break;
                                case 'contracts':
                                    if (isset($field['with_cdn']) && $field['with_cdn']) {
                                        if (strlen(trim($products[$i]['contracts'])) > 0) {
                                            $con_check = explode(',', $products[$i]['contracts']);

                                            if (count($con_check) > 1) {
                                                $all_contracts = '';
                                                for ($z = 0; $z < count($con_check); ++$z) {
                                                    $all_contracts .= env(
                                                        'AWS_CLOUDFRONT'
                                                    ) . '/' . $con_check[$z] . ' | ';
                                                }

                                                $row[$field['header']] = rtrim(
                                                    $all_contracts,
                                                    '|'
                                                );
                                            } else {
                                                $row[$field['header']] = env(
                                                    'AWS_CLOUDFRONT'
                                                ) . '/' . $products[$i]['contracts'];
                                            }
                                        } else {
                                            $row[$field['header']] = $products[$i]['contracts'];
                                        }
                                    } elseif (isset($field['file_only']) && $field['file_only']) {
                                        $parts = explode('/', $products[$i]['contracts']);
                                        $row[$field['header']] = $parts[count($parts) - 1];
                                    } else {
                                        $row[$field['header']] = $products[$i]['contracts'];
                                    }

                                    break;
                                case 'signature_page':
                                    $row[$field['header']] = config('app.urls.clients') . '/' . $products[$i]['signature_pages'];
                                    break;
                                case 'photos':
                                    if (isset($field['with_cdn']) && $field['with_cdn']) {
                                        if (strlen(trim($products[$i]['photos'])) > 0) {
                                            $con_check = explode(',', $products[$i]['photos']);

                                            if (count($con_check) > 1) {
                                                $all_photos = '';
                                                for ($z = 0; $z < count($con_check); ++$z) {
                                                    $all_photos .= env(
                                                        'AWS_CLOUDFRONT'
                                                    ) . '/' . $con_check[$z] . ' | ';
                                                }

                                                $row[$field['header']] = rtrim(
                                                    $all_photos,
                                                    '|'
                                                );
                                            } else {
                                                $row[$field['header']] = env(
                                                    'AWS_CLOUDFRONT'
                                                ) . '/' . $products[$i]['photos'];
                                            }
                                        } else {
                                            $row[$field['header']] = $products[$i]['photos'];
                                        }
                                    } elseif (isset($field['file_only']) && $field['file_only']) {
                                        $parts = explode('/', $products[$i]['photos']);
                                        $row[$field['header']] = $parts[count($parts) - 1];
                                    } else {
                                        $row[$field['header']] = $products[$i]['photos'];
                                    }

                                    break;
                                case 'intro_rate_amount':
                                    $row[$field['header']] = (isset($products[$i]['rate']['intro_rate_amount']))
                                    ? number_format($products[$i]['rate']['intro_rate_amount'], 4) : number_format(0, 4);
                                    break;
                                case 'product_rate_amount':
                                    $amount = (isset($products[$i]['product_rate_amount']))
                                        ? $products[$i]['product_rate_amount'] : 0;

                                    if (
                                        isset($field['currency'])
                                        && 'dollars' == $field['currency']
                                    ) {
                                        if (isset($products[$i]['product_rate_amount_currency'])) {
                                            if ($products[$i]['product_rate_amount_currency'] === 'cents') {
                                                $row[$field['header']] = $amount / 100;
                                            } else {
                                                $row[$field['header']] = number_format($amount, 4);
                                            }
                                        } else {
                                            // Default to cents
                                            $row[$field['header']] = $amount / 100;
                                        }
                                    } else {
                                        if (
                                            isset($products[$i]['product_rate_amount_currency'])
                                            && 'dollars' == $products[$i]['product_rate_amount_currency']
                                        ) {
                                            $row[$field['header']] = number_format($amount, 4);
                                        } else {
                                            $row[$field['header']] = $amount;
                                        }
                                    }

                                    break;
                                case 'name_key':
                                    $row[$field['header']]
                                        = trim($products[$i]['name_key']);
                                    break;
                                case 'btn':
                                    if (isset($field['tendigit']) && $field['tendigit']) {
                                        $row[$field['header']]
                                            = ltrim(trim($products[$i]['btn']), '+1');
                                    } else {
                                        $row[$field['header']]
                                            = trim($products[$i]['btn']);
                                    }
                                    break;
                                case 'account_numbers_joined':
                                    $row[$field['header']]
                                        = $products[$i]['account_number1'];

                                    if (
                                        isset($products[$i]['account_number2'])
                                        && strlen(trim($products[$i]['account_number2'])) > 0
                                    ) {
                                        $row[$field['header']] .= '-' . $products[$i]['account_number2'];
                                    }

                                    break;
                                case 'account_number1':
                                    $row[$field['header']] = '"' . $products[$i]['account_number1'] . '"';
                                    break;
                                case 'account_number1_raw':
                                    $row[$field['header']] = $products[$i]['account_number1'];
                                    break;
                                case 'account_number2':
                                    $row[$field['header']] = '"' . "\t" . $products[$i]['account_number2'] . '"';
                                    break;
                                case 'account_number2_raw':
                                    $row[$field['header']] = $products[$i]['account_number2'];
                                    break;
                                case 'service_address1':
                                    $row[$field['header']]
                                        = str_replace(',', ' ', $products[$i]['service_address1']);
                                    break;
                                case 'service_address_combined':
                                    $row[$field['header']]
                                        = str_replace(',', ' ', $products[$i]['service_address1'])
                                        . ' ' . str_replace(',', ' ', $products[$i]['service_address2']);
                                    break;
                                case 'billing_address1':
                                    $row[$field['header']]
                                        = str_replace(',', ' ', $products[$i]['billing_address1']);
                                    break;
                                case 'billing_address_combined':
                                    $row[$field['header']]
                                        = str_replace(',', ' ', $products[$i]['billing_address1'])
                                        . ' ' . str_replace(',', ' ', $products[$i]['billing_address2']);
                                    break;
                                case 'utility_label':
                                    $row[$field['header']]
                                        = @$products[$i]['rate']['utility']['utility']['brand_identifier']['utility_label'];
                                    break;
                                case 'service_zip':
                                    $row[$field['header']]
                                        = $products[$i]['service_zip'];
                                    break;
                                case 'billing_zip':
                                    $row[$field['header']]
                                        = $products[$i]['billing_zip'];
                                    break;
                                case 'product_monthly_fee':
                                    $row[$field['header']]
                                        = ($products[$i]['product_monthly_fee'] > 0)
                                        ? $products[$i]['product_monthly_fee']
                                        : 0;
                                    break;
                                default:
                                    if (
                                        isset($field['type'])
                                        && 'date' == $field['type']
                                    ) {
                                        $format = (isset($field['format']))
                                            ? $field['format'] : 'Y-m-d';

                                        $the_date = Carbon::parse(
                                            $products[$i][$field['field']]
                                        );

                                        if (
                                            isset($field['add_term'])
                                            && $field['add_term']
                                        ) {
                                            $term = $products[$i]['product_term'];
                                            $term_type = $products[$i]['product_term_type'];

                                            switch ($term_type) {
                                                case 'day':
                                                    $the_date = $the_date
                                                        ->addDays($term);
                                                    break;
                                                case 'week':
                                                    $the_date = $the_date
                                                        ->addWeeks($term);
                                                    break;
                                                case 'year':
                                                    $the_date = $the_date
                                                        ->addYear($term);
                                                    break;
                                                default:
                                                    $the_date = $the_date
                                                        ->addMonths($term);
                                            }

                                            $row[$field['header']] = $the_date
                                                ->format($format);
                                        } elseif (isset($field['add_days'])) {
                                            $row[$field['header']] = $the_date
                                                ->addDays($field['add_days'])
                                                ->format($format);
                                        } else {
                                            $row[$field['header']] = $the_date
                                                ->format($format);
                                        }
                                    } else {
                                        if (false !== strpos($field['field'], '.')) {
                                            $keys = explode('.', $field['field']);
                                            $new_field = $products[$i];
                                            foreach ($keys as $key) {
                                                $new_field = $new_field[$key];
                                            }

                                            if (isset($new_field)) {
                                                if (
                                                    isset($field['htmlentities'])
                                                    && $field['htmlentities']
                                                ) {
                                                    $row[$field['header']]
                                                        = htmlspecialchars(
                                                            $new_field,
                                                            ENT_QUOTES
                                                        );
                                                } elseif (
                                                    isset($field['uppercase'])
                                                    && $field['uppercase']
                                                ) {
                                                    $row[$field['header']]
                                                        = mb_strtoupper(
                                                            $new_field
                                                        );
                                                } else {
                                                    $row[$field['header']]
                                                        = $new_field;
                                                }
                                            } else {
                                                $row[$field['header']] = '';
                                            }
                                        } else {
                                            if (isset($products[$i][$field['field']])) {
                                                $field_text = $products[$i][$field['field']];
                                                if (
                                                    isset($field['prefix'])
                                                    && $field['prefix']
                                                ) {
                                                    $field_text = $field['prefix'] . $products[$i][$field['field']];
                                                }

                                                if (
                                                    isset($field['htmlentities'])
                                                    && $field['htmlentities']
                                                ) {
                                                    $row[$field['header']]
                                                        = htmlentities(
                                                            $field_text,
                                                            ENT_QUOTES
                                                        );
                                                } elseif (
                                                    isset($field['uppercase'])
                                                    && $field['uppercase']
                                                ) {
                                                    $row[$field['header']]
                                                        = mb_strtoupper(
                                                            $field_text
                                                        );
                                                } else {
                                                    $row[$field['header']]
                                                        = $field_text;
                                                }
                                            } else {
                                                $row[$field['header']] = '';
                                            }
                                        }
                                    }
                            }

                            break;
                    }
                }

                $enrollment[] = $row;
            }
        }

        return [
            'enrollment' => $enrollment,
            'products' => $products,
        ];
    }

    public function writeFile($brand, $data, $date)
    {
        $format = BrandEnrollmentFile::select(
            'file_formats.format'
        )->leftJoin(
            'file_formats',
            'brand_enrollment_files.file_format_id',
            'file_formats.id'
        )->where(
            'brand_enrollment_files.brand_id',
            $brand->brand_id
        )->first();
        $string = date('Y_m_d', strtotime($date)) . '_' . $brand->name;

        if ($format) {
            switch ($format->format) {
                case 'PSV':
                    $filename = strtolower(
                        trim(
                            preg_replace('#\W+#', '_', $string),
                            '_'
                        )
                    ) . '.csv';
                    $psv = $this->strPutcsv($data, '|');
                    file_put_contents(public_path('tmp/' . $filename), $psv);
                    $extension = '.csv';
                    break;
                case 'TSV':
                    $filename = strtolower(
                        trim(
                            preg_replace('#\W+#', '_', $string),
                            '_'
                        )
                    ) . '.txt';
                    $tsv = $this->strPutcsv($data, "\t");
                    file_put_contents(public_path('tmp/' . $filename), $tsv);
                    $extension = '.txt';
                    break;
                case 'XLS':
                    $filename = strtolower(
                        trim(
                            preg_replace('#\W+#', '_', $string),
                            '_'
                        )
                    ) . '.csv';
                    $csv = $this->strPutcsv($data, ',');
                    file_put_contents(public_path('tmp/' . $filename), $csv);

                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
                    $reader->setDelimiter(',');
                    $reader->setEnclosure('"');
                    $reader->setSheetIndex(0);

                    $spreadsheet = $reader->load(public_path('tmp/' . $filename));
                    $spreadsheet->getDefaultStyle()
                        ->getNumberFormat()
                        ->setFormatCode(
                            \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT
                        );

                    $xls = str_replace('.csv', '.xlsx', $filename);
                    $writer = new Xlsx($spreadsheet);
                    $writer->save(public_path('tmp/' . $xls));

                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                    unlink(public_path('tmp/' . $filename));
                    $filename = $xls;
                    $extension = '.xlsx';
                    break;
                case 'TXT':
                    $filename = strtolower(
                        trim(
                            preg_replace('#\W+#', '_', $string),
                            '_'
                        )
                    ) . '.txt';
                    $csv = $this->strPutcsv(str_replace('"', '', $data), ',', true);
                    file_put_contents(public_path('tmp/' . $filename), $csv);
                    $extension = '.txt';
                    break;
                default:
                    // CSV
                    $filename = strtolower(
                        trim(
                            preg_replace('#\W+#', '_', $string),
                            '_'
                        )
                    ) . '.csv';
                    $csv = $this->strPutcsv($data, ',');
                    $csv = str_replace('"""', '"', $csv);
                    file_put_contents(public_path('tmp/' . $filename), $csv);
                    $extension = '.csv';
            }

            $keyname = 'uploads/brands/'
                . $brand->brand_id . '/enrollments/'
                . md5($filename . time()) . $extension;
            try {
                $s3 = Storage::disk('s3')->put(
                    $keyname,
                    file_get_contents(public_path('tmp/' . $filename)),
                    'public'
                );
            } catch (\Aws\S3\Exception\S3Exception $e) {
                return false;
            }

            return [
                'filename' => $filename,
                's3filename' => $keyname,
            ];
        }

        return null;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->getBrands()->count() == 0) {
            $this->info('No brands were found.');
            exit();
        }

        foreach ($this->getBrands() as $brand) {

            // 2022-06-17 - Alex K - Wrap each brand in try/catch so any hard errors
            // for one brand will not affect other brands
            try {
                $this->info('Starting ' . $brand->name);
                $fields = $this->getReportFields($brand->bef_id);
                if (is_null($fields)) {
                    $this->info('-- No fields were found.');
                    continue;
                } else {
                    if ($this->option('date')) {
                        $date = $this->option('date');
                    } else {
                        $date = Carbon::yesterday()->format('Y-m-d');
                    }

                    $date2 = null;
                    if ($this->option('date2')) {
                        $date2 = $this->option('date2');
                    }

                    $data = $this->getData($brand, $fields, $date, $date2);

                    if ($this->option('debug')) {
                        print_r($data['enrollment']);
                        exit();
                    }

                    $enrollment = $data['enrollment'];
                    $products = $data['products'];
                    $file = $this->writeFile($brand, $enrollment, $date);

                    if (isset($file['filename'])) {
                        if ($this->option('keepfile')) {
                            $this->info('-- file is ' . public_path('tmp/' . $file['filename']));
                        } else {
                            if (!$this->option('noalert')) {
                                $dd = $this->getDeliveryData($brand->bef_id);

                                if (isset($dd['delivery_method'])) {
                                    switch ($dd['delivery_method']) {
                                        case 'sftp':
                                            $uploadStatus = $this->sftpUpload($dd, $file['filename']);
                                            break;
                                        case 'email':
                                            $uploadStatus = $this->emailUpload($dd, $file['filename']);
                                            break;
                                        default:
                                            // ftp
                                            $uploadStatus = $this->ftpUpload($dd, $file['filename']);
                                    }
                                }

                                $rh = $this->getRunHistory($brand->bef_id);

                                if (isset($uploadStatus)) {
                                    $rh[] = $uploadStatus;

                                    if ($dd) {
                                        $update = BrandEnrollmentFile::find($brand->bef_id);
                                        $update->next_run = $this->getNextRun($dd);
                                        $update->last_run = Carbon::now();
                                        $update->run_history = json_encode($rh);
                                        $update->save();
                                    }
                                }
                            }

                            $upload = new Upload();
                            $upload->brand_id = $brand->brand_id;
                            $upload->filename = $file['s3filename'];
                            $upload->upload_type_id = 5;
                            $upload->save();

                            $log1 = LogEnrollmentFile::where(
                                'brand_id',
                                $brand->brand_id
                            )->whereDate(
                                'start_date',
                                $date
                            )->whereDate(
                                'end_date',
                                ($date2 !== null) ? $date2 : $date
                            )->first();
                            if ($log1) {
                                $log1->delete();
                            }

                            $log2 = new LogEnrollmentFile();
                            $log2->brand_id = $brand->brand_id;
                            $log2->products = count($products);
                            $log2->start_date = $date;
                            $log2->end_date = ($date2 !== null) ? $date2 : $date;
                            $log2->upload_id = $upload->id;
                            $log2->save();

                            if (file_exists(public_path('tmp/' . $file['filename']))) {
                                unlink(public_path('tmp/' . $file['filename']));
                            }
                        }
                    }
                }
            }
            catch(\Exception $e) {
                $now = Carbon::now("America/Chicago");
                $errorMsg = "Exception occurred at $now CST when processing '{$brand->name}' enrollment file. Message: {$e->getMessage()}, line: {$e->getLine()}";

                // Display in console.
                $this->info($errorMsg);

                // Update run history with error.
                $rh = $this->getRunHistory($brand->bef_id);
                $rh[] = $errorMsg;

                // Find the record we want ot update
                $update = BrandEnrollmentFile::find($brand->bef_id);
                    
                if($update) {
                    $update->last_run = Carbon::now();
                    $update->run_history = json_encode($rh);
                    $update->save();
                }

                // Also log to Laravel log in case brand_enrollment_file record does not exist for some reason
                Log::error("CreateEnrollmentFiles.php :: " . $errorMsg);
            }
        }
    }
}
