<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\Request;

class TwilioTNInventoryController extends Controller
{
    public function generateReport(Request $request)
    {
        $apiData = $this->fetchDataFromApi();

        $mysqlData = $this->fetchDataFromMySQL($apiData);

        $mergedData = $this->mergeAndDeduplicateData($apiData, $mysqlData);

        $excelFilePath = $this->generateExcelFile($mergedData);

        $emails = $request->input('emails');

        $this->sendEmailWithExcelFile($excelFilePath, $emails);

        return response()->json(['message' => 'Twilio TN Inventory Monthly report generated and sent successfully']);
    }


    private function fetchDataFromApi()
    {

        $client = new Client();

        // Set the static JWT token for authentication
        $staticJwt = config('services.tpvapi.jwt');

        // Set the headers for the API request
        $headers = [
            'Authorization' => 'Bearer ' . $staticJwt,
        ];

        $response = $client->post('https://apiv2.tpvhub.com/api/util/getAllTwiliioPhoneNumbers', [
            'headers' => $headers,
        ]);

        if ($response->getStatusCode() == 200) {
            $apiData = json_decode($response->getBody(), true);
            $results = $apiData['results'];
            $data = [];

            // Extract the relevant data from the API response
            foreach ($results as $result) {
                $accountName = $result['AccountName']; 
                foreach ($result['phoneNumbers'] as $phoneNumber) {
                    $data[] = [
                        'SID' => $result['SID'],
                        'phone_number' => $phoneNumber['number'],
                        'AccountName' => $accountName, 
                    ];
                }
            }

            // Return the extracted API data
            return $data;
        }


        // Return an empty array if the API request failed
        return [];
    }


    private function fetchDataFromMySQL($apiData)
    {

        // Extract the phone numbers from the API data
        $phoneNumbers = array_column($apiData, 'phone_number');

        // Convert the phone numbers array into a comma-separated string
        $phoneNumbersString = implode(',', $phoneNumbers);

        // Construct the MySQL query to fetch data based on the phone numbers
        $mysqlQuery = "SELECT c.name as client_name, b.name as brand_name, d.dnis, d.dnis_type, d.platform, s.title as script_name, (SELECT max(e.created_at) FROM events e JOIN scripts s ON e.script_id = s.id WHERE s.dnis_id = d.id) as last_call_date, (SELECT max(t.created_at) FROM text_messages t WHERE t.from_dnis_id = d.id) as last_sms_date FROM dnis d JOIN brands b ON d.brand_id = b.id JOIN clients c ON b.client_id = c.id LEFT JOIN scripts s ON d.id = s.dnis_id WHERE d.dnis IN ($phoneNumbersString) AND d.platform IN ('focus', 'dxc')";

        // Execute the MySQL query and retrieve the results
        $mysqlData = DB::select($mysqlQuery);

        // Return the fetched MySQL data
        return $mysqlData;
    }


    private function mergeAndDeduplicateData($apiData, $mysqlData)
    {

        $mergedData = [];
        $apiPhoneNumberToSIDMap = [];

        // Create a map of phone numbers to SIDs and AccountName from the API data
        foreach ($apiData as $apiRow) {
            $phoneNumber = $apiRow['phone_number'];
            $sid = $apiRow['SID'];
            $accountName = $apiRow['AccountName'];

            if (!isset($apiPhoneNumberToSIDMap[$phoneNumber])) {
                $apiPhoneNumberToSIDMap[$phoneNumber] = [];
            }

            $apiPhoneNumberToSIDMap[$phoneNumber][] = ['SID' => $sid, 'AccountName' => $accountName];
        }

        $processedPhoneNumbers = [];

        // Merge the MySQL data with the corresponding API data
        foreach ($mysqlData as $row) {
            $phoneNumber = $row->dnis;
            $rowData = (array) $row;

            // Check if the phone number has already been processed
            if (!in_array($phoneNumber, $processedPhoneNumbers)) {
                $processedPhoneNumbers[] = $phoneNumber;

                // Check if the phone number exists in the API data map
                if (array_key_exists($phoneNumber, $apiPhoneNumberToSIDMap)) {
                    // Merge the MySQL row data with each corresponding API data entry
                    foreach ($apiPhoneNumberToSIDMap[$phoneNumber] as $sidData) {
                        $mergedData[] = array_merge($rowData, $sidData);
                    }
                } else {
                    // Add the MySQL row data as is if there is no corresponding API data
                    $mergedData[] = $rowData;
                }
            }
        }

        // Add SIDs and phone numbers from the API data that don't have matching data in MySQL
        $apiPhoneNumbers = array_keys($apiPhoneNumberToSIDMap);
        $mysqlPhoneNumbers = array_column($mergedData, 'dnis');
        $unmergedPhoneNumbers = array_diff($apiPhoneNumbers, $mysqlPhoneNumbers);

        foreach ($unmergedPhoneNumbers as $phoneNumber) {
            // Add each unmerged phone number and its corresponding SID and AccountName to the merged data
            foreach ($apiPhoneNumberToSIDMap[$phoneNumber] as $sidData) {
                $mergedData[] = array_merge([
                    'client_name' => '',
                    'brand_name' => '',
                    'dnis' => $phoneNumber,
                    'dnis_type' => '',
                    'platform' => '',
                    'script_name' => '',
                    'last_call_date' => '',
                    'last_sms_date' => ''
                ], $sidData);
            }
        }

        // Return the merged and deduplicated data
        return $mergedData;
    }

    private function generateExcelFile($data)
    {

        // Create a new Spreadsheet instance
        $spreadsheet = new Spreadsheet();

        // Get the active sheet
        $sheet = $spreadsheet->getActiveSheet();

        // Set column widths for each column
        $sheet->getColumnDimension('A')->setWidth(30); // Client Name
        $sheet->getColumnDimension('B')->setWidth(30); // Brand Name
        $sheet->getColumnDimension('C')->setWidth(20); // DNIS
        $sheet->getColumnDimension('D')->setWidth(15); // DNIS Type
        $sheet->getColumnDimension('E')->setWidth(15); // Platform
        $sheet->getColumnDimension('F')->setWidth(30); // Script Name
        $sheet->getColumnDimension('G')->setWidth(20); // Last Call Date
        $sheet->getColumnDimension('H')->setWidth(20); // Last SMS Date
        $sheet->getColumnDimension('I')->setWidth(40); // SID
        $sheet->getColumnDimension('J')->setWidth(25); // Account Name

        // Define header style
        $headerStyle = [
            'font' => [
                'color' => [
                    'rgb' => 'FFFFFF',
                ],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '000000',
                ],
            ],
        ];

        // Define header row
        $headerRow = [
            'Client Name',
            'Brand Name',
            'DNIS',
            'DNIS Type',
            'Platform',
            'Script Name',
            'Last Call Date',
            'Last SMS Date',
            'Twilio Sub Account Sid',
            'Twilio Sub Account Name',
        ];

        // Write the header row to the sheet
        $sheet->fromArray([$headerRow], null, 'A1');

        // Apply the header style to the first row
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        // Write data to the sheet
        $rowCount = 2;
        foreach ($data as $row) {
            $dnis = $row['dnis'];
            $formattedDnis = $this->formatDnis($dnis); 

            // Set cell values for each column in the current row based on the postion from $mergedData
            $sheet->setCellValue('A' . $rowCount, $row['client_name']);
            $sheet->setCellValue('B' . $rowCount, $row['brand_name']);
            $sheet->setCellValue('C' . $rowCount, $formattedDnis); // Use the formatted DNIS
            $sheet->setCellValue('D' . $rowCount, $row['dnis_type']);
            $sheet->setCellValue('E' . $rowCount, $row['platform']);
            $sheet->setCellValue('F' . $rowCount, $row['script_name']);
            $sheet->setCellValue('G' . $rowCount, $row['last_call_date']);
            $sheet->setCellValue('H' . $rowCount, $row['last_sms_date']);
            $sheet->setCellValue('I' . $rowCount, $row['SID']);
            $sheet->setCellValue('J' . $rowCount, $row['AccountName']);
            $rowCount++;
        }

        // Get the data as an array (excluding the header row)
        $dataArray = $sheet->rangeToArray('A2:J' . ($rowCount - 1), null, true, true, true);

        // Sort the data by 'Client Name' column in ascending order (A-Z)
        usort($dataArray, function ($a, $b) {
            // Move rows with blank 'Client Name' to the end
            if ($a['A'] === '' && $b['A'] !== '') {
                return 1;
            } elseif ($a['A'] !== '' && $b['A'] === '') {
                return -1;
            }

            // Sort non-blank 'Client Name' values in ascending order
            return strcmp($a['A'], $b['A']);
        });

        // Clear the existing data in the sheet (excluding the header row)
        $sheet->removeRow(2, $sheet->getHighestRow());

        // Write the sorted data back to the sheet
        $sheet->fromArray($dataArray, null, 'A2');

        // Save the Excel file
        $writer = new Xlsx($spreadsheet);
        $filePath = Storage::path('TPVTwilioTNInventory.xlsx');
        $writer->save($filePath);

        // Return the file path of the generated Excel file
        return $filePath;
    }


    private function formatDnis($dnis)
    {
        // Remove leading +1 if it exists
        $dnis = ltrim($dnis, '+1');

        // Check if the DNIS has a length of 10 digits
        if (strlen($dnis) == 10) {
            // Format the DNIS as friendly format (XXX) XXX-XXXX
            $formattedDnis = "({$dnis[0]}{$dnis[1]}{$dnis[2]}) {$dnis[3]}{$dnis[4]}{$dnis[5]}-$dnis[6]{$dnis[7]}{$dnis[8]}{$dnis[9]}";
        } else {
            // Return the original DNIS if it doesn't match the 10-digit pattern
            $formattedDnis = $dnis;
        }

        // Return the formatted DNIS
        return $formattedDnis;
    }

    private function sendEmailWithExcelFile($filePath, $emails)
    {
        // Send the email with the Excel file attachment
        Mail::send([], [], function ($message) use ($filePath, $emails) {
            $message->attach($filePath)
                ->to($emails)
                ->subject('Twilio TN Inventory Monthly Report');
        });
    }
}
