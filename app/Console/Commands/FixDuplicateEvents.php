<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FixDuplicateEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:duplicate:events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark duplicate event entries as synced based on their confirmation code.';

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
    public function handle(): void
    {
        try {
            $duplicateEvents = $this->getDuplicateEvents();
            $this->line("Found " . count($duplicateEvents) . " duplicate events");
            Log::info("FixDuplicateEvents: Found " . count($duplicateEvents) . " duplicate events");

            if ($duplicateEvents->isEmpty()) {
                $this->line("No duplicate events found, exiting...");
                Log::info("FixDuplicateEvents: No duplicate events, exited job.");
                return;
            }

            $reportPath = $this->saveDuplicateEvents($duplicateEvents);
            $this->line("Report saved to " . $reportPath);
            Log::info("FixDuplicateEvents: Duplicate events report saved to " . $reportPath);

            if ($this->confirm('Do you wish to mark these duplicate events as synced?')) {
                $fixedSyncedReport = $this->markEventsAsSynced($duplicateEvents);
                $this->info("Duplicate events marked as synced report saved to " . $fixedSyncedReport);
                Log::info("FixDuplicateEvents: Duplicate events marked as synced report saved to " . $fixedSyncedReport);
            }
        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            Log::error("FixDuplicateEvents: Error in handle method: " . $e->getMessage(), ['exception' => $e]);
        }
    }

    private function getDuplicateEvents()
    {
        try {
            return Event::select('events.brand_id', 'events.id', 'events.created_at', 'events.confirmation_code', 'events.sales_agent_id', 'events.synced')
                ->whereIn('events.confirmation_code', function ($query) {
                    $query->select('confirmation_code')
                        ->from('events')
                        ->groupBy('confirmation_code')
                        ->havingRaw('COUNT(*) > 1');
                })
                ->orderBy('events.brand_id')
                ->orderBy('events.confirmation_code')
                ->get();
        } catch (\Exception $e) {
            Log::error("FixDuplicateEvents: Error in getDuplicateEvents: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    private function saveDuplicateEvents($duplicateEvents): string
    {
        try {
            $csvOutput = '';

            if (!empty($duplicateEvents)) {
                $headers = array_keys($duplicateEvents[0]->toArray());
                $csvOutput .= implode(',', $headers) . "\n";
            }

            foreach ($duplicateEvents as $event) {
                $csvOutput .= implode(',', $event->toArray()) . "\n";
            }

            $filename = 'duplicate_events_' . now()->format('Y-m-d_H-i-s') . '.csv';
            Storage::disk('local')->put($filename, $csvOutput);

            return storage_path('app\\' . $filename);
        } catch (\Exception $e) {
            Log::error("FixDuplicateEvents: Error in saveDuplicateEvents: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    private function markEventsAsSynced($duplicateEvents): string
    {
        try {
            $syncedReport = [];
            $eventDetails = [];

            foreach ($duplicateEvents as $event) {
                if ($event->synced == 1) {
                    $eventDetails[$event->id][] = "Event was already synced.";
                    $this->line("Skipping " . $event->confirmation_code . " because it's already synced.");
                } else {
                    $syncedDuplicates = Event::where('confirmation_code', $event->confirmation_code)
                        ->where('brand_id', $event->brand_id)
                        ->where('created_at', $event->created_at)
                        ->where('sales_agent_id', $event->sales_agent_id)
                        ->where('synced', 1)
                        ->get();

                    if (!$syncedDuplicates->isEmpty()) {
                        $eventModel = Event::find($event->id);
                        $eventModel->synced = 1;
                        $eventModel->save();
                        $eventDetails[$event->id][] = "Event was updated and marked as synced.";
                        $this->line("Updated " . $event->confirmation_code . " synced to 1.");
                        Log::info("FixDuplicateEvents: Event marked as synced", ['event_id' => $event->id]);
                    } else {
                        $eventDetails[$event->id][] = "Event was not synced because an exact match was not found or is not synced.";
                        $this->line("Event: " . $event->confirmation_code . " was not synced because an exact match was not found.");
                    }
                }
                $syncedReport[] = ['event' => $event, 'details' => $eventDetails[$event->id]];
            }

            if (!empty($syncedReport)) {
                $csvOutput = '';

                $headers = array_keys($syncedReport[0]['event']->getAttributes());
                $headers[] = 'details';
                $csvOutput .= implode(',', $headers) . "\n";

                foreach ($syncedReport as $record) {
                    $eventArray = $record['event']->getAttributes();
                    $detailsAsString = implode('; ', $record['details']);
                    $eventArray['details'] = $detailsAsString;

                    $csvOutput .= implode(',', $eventArray) . "\n";
                }

                $filename = 'synced_report_' . now()->format('Y-m-d_H-i-s') . '.csv';
                Storage::disk('local')->put($filename, $csvOutput);

                return storage_path('app\\' . $filename);
            }
            return '';
        } catch (\Exception $e) {
            Log::error("FixDuplicateEvents: Error in markEventsAsSynced: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}
