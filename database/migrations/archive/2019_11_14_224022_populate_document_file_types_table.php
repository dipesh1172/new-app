<?php

use App\Models\DocumentFileType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PopulateDocumentFileTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $types = [
            'PDF',
            'DOC'
        ];

        foreach ($types as $type) {
            $dfts = new DocumentFileType();
            $dfts->file_type = $type;
            $dfts->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // unnecessary
    }
}
