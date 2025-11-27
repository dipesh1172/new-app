<?php

namespace App\Mail;

use App\Models\Disposition;
use App\Models\EventAlert;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class EventAlertTripped extends Mailable implements ShouldQueue #implements ShouldQueue MUST BE ENABLED IN PRODUCTION/ DISABLED IN STAGING
{
    use Queueable, SerializesModels;

    public $alert;
    public $emailType;

    /**
     * Create a new message instance.
     *
     * @param EventAlert $alert the alert that was triggered
     *
     * @return void
     */
    public function __construct(EventAlert $alert, string $emailType)
    {
        $this->alert = $alert;
        $this->emailType = $emailType;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->from('no-reply@tpvhub.com');

        $subject = "";
        if (config('app.env') != 'production') {
            $subject .= "(" . config('app.env') . ") ";
        }

        if ($this->alert->event && $this->alert->event->brand && $this->alert->event->brand->name) {
            $subject .= $this->alert->event->brand->name . " - ";
        }

        info("ALERT OBJECT is :" . json_encode($this->alert));

        //Getting Disposition name to be added to the subject
        $dispositionReason="";
        
        try{
            if (isset($this->alert->data['data'][0]['disposition'])) {
                info("DISPOSITION code is :" . $this->alert->data['data'][0]['disposition']);
                $dispo = Disposition::find($this->alert->data['data'][0]['disposition']);
                $dispositionReason = ' - ' . $dispo->reason;
            }
        } catch (\Exception $e) {
            info('Error getting Disposition name on EventAlertTripped '. $e->getMessage());
            SendTeamMessage('monitoring', 'Error getting Disposition name on EventAlertTripped '. $e->getMessage() );
        }

        $this->subject($subject . 'Alert: ' . $this->alert->client_alert->title . $dispositionReason);

        $genieBrands = [
            '0e80edba-dd3f-4761-9b67-3d4a15914adb', // Residents prod and stage
            '77c6df91-8384-45a5-8a17-3d6c67ed78bf', // IDT Energy prod and stage
            '872c2c64-9d19-4087-a35a-fb75a48a1d0f', // Townsquare prod
            'dda4ac42-c7b8-4796-8230-9668ad64f261', // Townsquare staging
        ];

        // Check if Assoc Array values are set.  If they are, use them, else empty string
        $brand = (isset($this->alert->data['brand_id'])) ? $this->alert->data['brand_id'] : '';
        $btn = (isset($this->alert->data['data'][0]['phone'])) ? $this->alert->data['data'][0]['phone'] : '';
        $accountNumber = null;

        if (isset($this->alert->data['data'][0]['product']['selection'])) {
            foreach ($this->alert->data['data'][0]['product']['selection'] as $selectionKey => $selection) {
                foreach($selection as $selection2){
                    $accountNumber = '';
            
                    if (isset($selection2['identifiers'])) {
                        foreach ($selection2['identifiers'] as $ident) {
                            if (isset($ident['utility_account_number_type_id']) && $ident['utility_account_number_type_id'] == 1) {
                                // Check if 'ident' key exists before accessing it
                                if (isset($ident['ident'])) {
                                    $accountNumber = $ident['ident'];
                                } else {
                                    $accountNumber = $ident['identifier'];
                                }
                                break; // Exit the loop once the account number is found
                            }
                        }
                    }
                }
            }
        }  

        return $this
            ->view('emails.event-has-alert')
            ->with([
                'alert' => $this->alert,
                'brand' => $brand,
                'btn' => $btn,
                'accountNumber' => $accountNumber, 
                'genieBrands' => $genieBrands,
                'emailType' => $this->emailType
            ]);
    }
}
