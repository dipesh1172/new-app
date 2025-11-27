<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SoapClient;
use SoapFault;

class SoapCommander extends Command
{
    private $userAgent;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'soap:test 
        {--soap-version= : 11 for 1.1 or 12 for 1.2, defaults to 1.1} 
        {--wsdl= : URL of WSDL file} 
        {--method= : The Method to call} 
        {--input-file= : File containing json data for input} 
        {--input= : JSON Data for input}
        {--describe : Print debug information about the API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tests a Soap command';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->userAgent = 'TPV.com Focus SOAP Tester/0.9';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('describe') == null && $this->option('input') == null && $this->option('input-file') == null) {
            $this->error('You must provide input for the method parameters');
            die;
        }
        $rawSoapVersion = $this->option('soap-version');
        switch ($rawSoapVersion) {
            default:
            case '11':
                $soapVersion = \SOAP_1_1;
                break;

            case '12':
                $soapVersion = \SOAP_1_2;
                break;
        }

        $context = [
            'http' => [
                'ignore_errors' => true,
                'user_agent' => $this->userAgent,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'crypto_method' => \STREAM_CRYPTO_METHOD_TLS_CLIENT
            ]
        ];

        $client = new SoapClient(
            $this->option('wsdl'),
            [
                'user_agent' => $this->userAgent,
                'soap_version' => $soapVersion,
                'trace' => true,
                'exception' => true,
                'cache_wsdl' => \WSDL_CACHE_NONE,
                'stream_context' => stream_context_create($context)
            ]
        );

        if ($this->option('describe')) {
            $funcs = $client->__getFunctions();
            $types = $client->__getTypes();
            $this->info('The API defines these types:');
            $this->line('');
            foreach ($types as $type) {
                $this->line($type);
                $this->line('');
            }
            $this->line('');
            $this->info('The API defines these functions:');
            $this->line('');
            foreach ($funcs as $func) {
                $this->line('   ' . $func);
                $this->line('');
            }
            $this->line('');
        } else {

            $method = $this->option('method');
            if ($this->option('input-file')) {
                $inputData = json_decode(file_get_contents($this->option('input-file')));
            } else {
                $inputData = json_decode($this->option('input'));
            }

            try {
                $client->$method($inputData);
            } catch (SoapFault $e) {
                $this->info('Error During Processing:');
                $this->error($e->getMessage());
                $this->line('');
            } finally {
                $this->info('Request:');
                $request = $client->__getLastRequest();
                if ($request !== null) {
                    $this->line($request);
                } else {
                    $this->warn('Request Not Sent');
                }
                $this->line('');
                $this->info('Response:');
                $response = $client->__getLastResponse();
                if ($response !== null) {
                    $this->line($response);
                } else {
                    $this->warn('No Response Given');
                }
            }
        }
    }
}
