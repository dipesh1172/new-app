<?php

namespace App\Console\Commands;

use setasign\Fpdi\Fpdi;
use Illuminate\Console\Command;

class MergePDF extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pdf:merge {--output=} {inputFiles*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge the passed PDF files into one.';

    /**
     * Create a new command instance.
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
        $fpdi = new Fpdi();
        $filesToMerge = $this->argument('inputFiles');
        if (count($filesToMerge) == 0) {
            $this->error('No Input Files specified');

            return 2;
        }
        if (count($filesToMerge) == 1) {
            $this->error('Must specify more than one file to merge.');

            return 3;
        }
        $outputFile = $this->option('output');

        foreach ($filesToMerge as $filename) {
            if (!file_exists($filename)) {
                $this->error('The input file: ' . $filename . ' does not exist.');
                return 4;
            }
            $count = $fpdi->setSourceFile($filename);
            for ($i = 1; $i <= $count; ++$i) {
                $template = $fpdi->importPage($i);
                $size = $fpdi->getTemplateSize($template);
                //$this->info(json_encode($size));
                $fpdi->AddPage(isset($size['orientation']) ? $size['orientation'] : 'P', [$size['width'], $size['height']]);
                $fpdi->useTemplate($template);
            }
        }

        if ($outputFile == null) {
            $output = $fpdi->Output('S', 'newfile.pdf', true);
            $this->line($output);
        } else {
            $fpdi->Output('F', $outputFile, true);
        }
    }
}
