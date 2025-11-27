<?php

namespace App\Console\Commands;

use App\Models\TpvStaff;
use Illuminate\Console\Command;
use GuzzleHttp\Client as GuzzleClient;

class ClientAlertCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert:check 
                                {--user=auto : User ID of TPV Staff to get credentials from} 
                                {--payload= : Send Payload from file directly} 
                                {--random : Generate random data} 
                                {--brand= : Brand ID to set for random data}
                                {--category= : used along with random option to specify category to generate}
                                {--debug : Display extra info for request}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs the payload through the alert system';

    private $templates;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->templates = [
            'CALL-START' => [
                'event' => null,
                'agent' => null,
                'calledFrom' => null,
                'calledInto' => null,
                'interaction' => null,
            ],
            'CUST-INFO-PROVIDED' => [
                'auth_name' => [
                    'first_name' => null,
                    'middle_name' => null,
                    'last_name' => null,
                ],
                'phone' => null,
                'email' => null,
                'event' => null,
                'agent' => null,
                'interaction' => null,
            ],
            'ACCT-INFO-PROVIDED' => [
                'product' => [
                    'isNew' => true,
                    'addresses' => [
                        'service' => [],
                        'billing' => [],
                    ],
                    'extra_fields' => null,
                    'bill_first_name' => null,
                    'bill_middle_name' => null,
                    'bill_last_name' => null,
                    'auth_relationship' => null,
                    'market_id' => null,
                    'event_type_id' => null,
                    'home_type_id' => null,
                    'selection' => [
                        '{utility}' => [
                            'identifiers' => [
                                [
                                    'ident' => null,
                                    'utility_account_type_id' => null,
                                    'valid' => true,
                                ]
                            ],
                            'product' => null,
                            'fuel_id' => null,
                            'fuel_type' => null,
                            'customFields' => []
                        ]
                    ]
                ]
            ],
            'DISPOSITIONED' => [
                'event' => null,
                'agent' => null,
                'calledFrom' => null,
                'calledInto' => null,
                'interaction' => null,
                'callReview' => [
                    'reason' => null,
                    'notes' => null
                ],
                'result' => null,
                'disposition' => null,
                'callTime' => [
                    'total' => null,
                    'current' => null,
                    'current_interaction' => null,
                    'countUp' => true,
                ]
            ]
        ];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $debug = $this->option('debug');
        $token = $this->getUserApiToken($this->option('user'));
        if ($token == null && $this->option('user') !== 'auto') {
            $this->error('The specified user is not logged in.');
            return 42;
        }
        $payload = null;
        if ($this->option('payload') !== null) {
            if (file_exists($this->option('payload'))) {
                $payload = json_decode(file_get_contents($this->option('payload')), true);
            } else {
                $this->error('The specificed payload file ' . $this->option('payload') . ' does not exist or cannot be found.');
                return 42;
            }
        }
        $category = $this->option('category');
        $random = $this->option('random');
        $brand = $this->option('brand');
        if ($random) {
            // TODO: Remove this once implemented
            $this->error('Not Implemented');
            return 42;
            if ($brand === null) {
                $this->error('You must specify a brand for random testing');
                return 42;
            }
            if ($category === null) {
                $this->error('You must specify a category for random testing.');
                return 42;
            }
            if ($token === null) {
                $this->error('The token is invalid for random testing');
                return 42;
            }
            $this->info('');
            $this->info($this->generateRandomFor($category, $token, $brand));
            return 0;
        }

        if ($payload === null) {
            $this->error('There is no payload to send.');
            return 42;
        }
        if ($token === null) {
            $this->error('API Token is Invalid');
            return 42;
        }

        $payload['token'] = $token;
        $payloadData = $payload['data'];
        unset($payload['data']);

        $this->info('Payload:');
        $this->info(json_encode($payloadData, \JSON_PRETTY_PRINT));
        $this->line('');

        $gc = new GuzzleClient();
        $response = $gc->request(
            'POST',
            config('app.urls.mgmt') . '/alerts/check',
            [
                'debug' => $this->option('debug'),
                'allow_redirects' => false, // a redirect is an error
                'verify' => false, // allow self signed ssl certs
                'query' => $payload,
                'form_params' => [
                    'data' => $payload
                ]
            ]
        );
        $headers = $response->getHeaders();
        if ($debug) {
            $this->info('------------------');
            $this->info('Headers');
            $this->info('------------------');
            foreach ($headers as $key => $values) {
                $this->info($key . ': ' . $values[0]);
            }
            $this->info('------------------');
        }
        if ($response->getStatusCode() == 200) {
            $body = $response->getBody()->getContents();
            if ($response->getHeader('Content-Type')[0] == 'application/json') {
                $body = json_decode($body, true);
            }
            if ($debug) {
                $this->info('Body:');
                $this->info('------------------');
                if ($response->getHeader('Content-Type')[0] == 'application/json') {
                    $this->info(json_encode($body, \JSON_PRETTY_PRINT));
                } else {
                    $this->info($body);
                }
                $this->line('');
            }

            if (is_array($body)) {
                $this->line('');
                $this->info('Response:');
                $this->info('Errors: ' . (count(array_filter($body['errors'])) == 0 ? 'None' : 'Yes'));
                $this->info('Would Stop the Call? ' . (count(array_filter($body['stop-call'])) == 0 ? 'No' : 'Yes'));
                if (count(array_filter($body['message'])) > 0) {
                    $this->info('Messages: ' . implode(',', $body['message']));
                }
                if (count(array_filter($body['disposition'])) > 0) {
                    $this->info('Use disposition(s): ' . implode(',', $body['disposition']));
                }
            } else {
                $this->info('Potential invalid response, got:');
                $this->info($body);
            }
        } else {
            if (in_array($response->getStatusCode(), [301, 302, 307, 308])) {
                $this->error('Redirected to ' . $response->getHeader('Location')[0]);
            } else {
                $this->error('Error ' . $response->getStatusCode() . ' => ' . $response->getReasonPhrase());
            }
        }
        if ($debug) {
            $this->info('------------------');
        }
        $this->line('');
    }

    private function generateRandomFor($category, $token, $brand)
    {
        $out = [];
        $out['token'] = $token;
        $out['type'] = $category;
        $out['brand'] = $brand;
        $out['data'] = [];
        return json_encode($out, \JSON_PRETTY_PRINT);
    }

    private function getUserApiToken($id)
    {
        if ($id === 'auto') {
            $user = TpvStaff::whereNotNull('api_token')->orderBy('updated_at', 'DESC')->first();
            $this->info('Using user ' . $user->first_name . ' ' . $user->last_name . ' (' . $user->username . ') id: ' . $user->id);
        } else {
            $user = TpvStaff::find($id);
        }
        return $user->api_token;
    }
}
