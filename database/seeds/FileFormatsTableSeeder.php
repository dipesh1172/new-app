<?php

use App\Models\FileFormat;
use Illuminate\Database\Seeder;

class FileFormatsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $exists = FileFormat::where('format', 'CSV')->first();
        if ($exists == null) {
            $format = new FileFormat;
            $format->format = 'CSV';
            $format->save();
        } else {
            echo " CSV already exists.\n";
        }

        $exists = FileFormat::where('format', 'TSV')->first();
        if ($exists == null) {
            $format = new FileFormat;
            $format->format = 'TSV';
            $format->save();
        } else {
            echo " TSV already exists.\n";
        }

        $exists = FileFormat::where('format', 'XLS')->first();
        if ($exists == null) {
            $format = new FileFormat;
            $format->format = 'XLS';
            $format->save();
        } else {
            echo " XLS already exists.\n";
        }

        echo "file_formats seeding complete.\n";
    }
}
