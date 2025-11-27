<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Aws\Credentials\Credentials;
use Aws\Credentials\CredentialProvider;
use Aws\Sqs\SqsClient; 
use Aws\Exception\AwsException;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

use Symfony\Component\Console\Output\BufferedOutput;

use App\Models\Event;

/**
 * Expected Payload:
 * 
 * {
 *   "eventId": "string",
 *   "options": "string array",
 *   "data": "object array"
 * }
 */
class ContractGeneratorApiController extends Controller 
{
    /**
     * Queues a contract in AWS SQS for processing.
     * 
     * The function creates an AWS SQS message containing a JSON object with an 'eventId' property.
     * 
     * @param Event $eventData - The event data we
     */
    public static function queueContract($eventData) {

        // TODO: Look into using a Laravel Validator for these validations

        // TODO: This null check may not be needed here since $eventData is not optional.
        info("##### ContractGeneratorApiController::queueContract - Validating args...");
        if(!$eventData) {
            $result = (object)[
                'result' => 'error',
                'message' => 'Missing $eventData',
                'data' => null
            ];

            info("##### ContractGeneratorApiController::queueContract - Error:", [$result]);
            return $result;
        }

        // event_id propert must exist and cannot be null
        if(!isset($eventData->event_id) || !$eventData->event_id) {
            $result = (object)[
                'result' => 'error',
                'message' => 'eventData->event_id is required',
                'data' => null
            ];

            info("##### ContractGeneratorApiController::queueContract - Error:", [$result]);
            return $result;
        }

        // confirmation_code must exist and cannot be null
        if(!isset($eventData->confirmation_code) || !$eventData->confirmation_code) {
            $result = (object)[
                'result' => 'error',
                'message' => 'eventData->confirmation_code is required',
                'data' => null
            ];

            info("##### ContractGeneratorApiController::queueContract - Error:", [$result]);
            return $result;
        }

        // event_created_at must exist and cannot be null
        if(!$eventData->event_created_at) {
            $result = (object)[
                'result' => 'error',
                'message' => 'eventData->event_created_at is required',
                'data' => null
            ];

            info("##### ContractGeneratorApiController::queueContract - Error:", [$result]);
            return $result;
        }

        // Create the SQS client
        info("##### ContractGeneratorApiController::queueContract - Creating SQS client...");
        $sqsClient = new SqsClient([
            'credentials' => CredentialProvider::fromCredentials(new Credentials(config('services.aws.contracts_sqs.key'), config('services.aws.contracts_sqs.secret'))),
            'region' => config('services.aws.region'),
            'version' => 'latest',
        ]);

        // Create the request payload
        // This is where we will create an array with any fields we want to pass in the SQS message.
        // It'll be converted to a JSON object.
        info("##### ContractGeneratorApiController::queueContract - Creating SQS data payload...");
        $options = [];
        if(isset($eventData->options)) {
            $options = $eventData->options;
        }

        $queueName = (config('app.env') === 'production') ? 'indra-contract' : 'contract-dev';

        $data = [
            "confirmationCode" => $eventData->confirmation_code,
            "options" => $options
        ];

        // Setup the SQS request
        $params = [
            'DelaySeconds' => 0,
            'MessageAttributes' => [
                'ConfirmationCode' => [
                    'DataType' => 'String',
                    'StringValue' => $eventData->confirmation_code
                ],
                'EventCreatedAt' => [
                    'DataType' => 'String',
                    'StringValue' => $eventData->event_created_at
                ]
            ],
            'MessageBody' => json_encode($data),
            'QueueUrl' => 'https://sqs.' . config('services.aws.region') . '.amazonaws.com/489375306559/' . $queueName
        ];

        info("##### ContractGeneratorApiController::queueContract - Data payload:", [$params]);

        // Send the request and check for errors
        try {
            info("##### ContractGeneratorApiController::queueContract - Posting data...");
            $result = $sqsClient->sendMessage($params);

            $statusCode = $result->toArray()['@metadata']['statusCode']; // TODO: Feels like there should be a better way to get this status code...

            $result = (object)[
                'result' => ($statusCode == 200 ? 'success' : 'error'),
                'message' => '',
                'data' => $result
            ];

            info("##### ContractGeneratorApiController::queueContract - Post result:", [$result]);
            return $result;

        } catch (AwsException $e) {

            $result = (object)[
                'result' => 'error',
                'message' => 'Exception: ' . $e->getMessage(),
                'data' => null
            ];

            info("##### ContractGeneratorApiController::queueContract - Post error:", [$result]);
            return $result;
        }
    }

    /**
     * // TODO: Implement and document
     */
    public function generateContract(Request $request) {

        info("##### ContractGeneratorApiController::generateContract - Validating args...");

        if(!$request->confirmationCode) {
            return $this->newHttpResponse('error', 'Missing required field: confirmationCode');
        }

        // Parse optional parameters for Laravel command
        info("##### ContractGeneratorApiController::generateContract - Building Artisan command options...");
        $opts = [
            '--confirmation_code' => $request->confirmationCode
        ];
        if (config('app.env') === 'local') {
            $opts['--override-local'] = true;
        }

        if($request->options && is_array($request->options)) {
            foreach($request->options as $key => $value) {
                $opts[$key] = $value;
            }
        }

        info("##### ContractGeneratorApiController::generateContract - Artisan command options:", [$opts]);

        try {
            info("##### ContractGeneratorApiController::generateContract - Calling Artisan command...");
            // Artisan::call('eztpv:generateContracts', $opts);

            ob_start();
            $output = new BufferedOutput();

            Artisan::queue('eztpv:generateContracts', $opts, $output);
            $out1 = ob_get_contents();
            ob_end_clean();

            $output = $out1 . "\n" . $output->fetch();
            info("##### Contract Generator Output:", [$output]);

        } catch (\Exception $e) {
            info("##### ContractGeneratorApiController::generateContract - Error:", [$e->getMessage()]);
            return $this->newHttpResponse('error', $e->getMessage());
        }

        return $this->newHttpResponse('success');
    }

    /**
     * Result object. Used for results communication within the class.
     * 
     * @return object - The result object
     */
    private function newResult($result, $message = "", $data = null) {
        return (object)[
            "result" => $result,
            "message" => $message,
            "data" => $data
        ];
    }

    /**
     * Returns an HTTP response. Utilizes $this->newResult and encapsulates it in a response()
     * 
     * @return Response - The HTTP Response
     */
    private function newHttpResponse($result, $message = "", $data = null) {
        return response()->json(
            $this->newResult($result, $message, $data)
        );
    }       
}
