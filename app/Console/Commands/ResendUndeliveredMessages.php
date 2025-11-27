<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\TextMessage;

class ResendUndeliveredMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:resend-undelivered {--brand=} {--date=} {--dryrun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resends Undelivered SMS';

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
        $dateRaw = $this->option('date');
        $date = null;

        if (!empty($dateRaw)) {
            $date = Carbon::parse($dateRaw, 'America/Chicago')->format('Y-m-d');
        } else {
            $date = Carbon::now('America/Chicago')->format('Y-m-d');
        }
        $brandId = $this->option('brand');

        $toResend = TextMessage::with(['to_phone', 'from_dnis'])
            ->whereDate('created_at', $date)
            ->where('status', 'undelivered');

        if (!empty($brandId)) {
            $toResend = $toResend->where('brand_id', $brandId);
        }
        $toResend = $toResend->get();

        $cnt = $toResend->count();
        if ($cnt === 0) {
            $this->warn('No undelivered messages to resend');
        }
        $bar = $this->output->createProgressBar($cnt);
        $bar->start();

        $toResend->each(function ($item) use ($bar) {
            if (!empty($item->from_dnis_id)) {
                $from = $item->from_dnis->dnis;
            }
            if (empty($from)) {
                $from = config('services.twilio.default_number');
            }
            if (!$this->option('dryrun')) {
                SendSMS($item->to_phone->phone_number, $from, $item->content, $item->sender_id, $item->brand_id, $item->text_message_type_id);
            } else {
                $this->info('Would of sent: "' . $item->content . '" to ' . $item->to_phone_phone_number);
            }
            $bar->advance();
        });

        $bar->finish();
    }
}
