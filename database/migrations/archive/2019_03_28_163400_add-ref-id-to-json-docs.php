<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\JsonDocument;

class AddRefIdToJsonDocs extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('json_documents', function (Blueprint $table) {
            $table->string('ref_id', 36)->nullable();
        });

        $uploads = JsonDocument::where('document_type', '<>', 'stats-job')->where('document_type', 'LIKE', '%-%')->get();
        foreach ($uploads as $upload) {
            $upload->ref_id = $upload->document_type;
            $upload->document_type = 'upload-errors';
            $upload->save();
        }

        $errors = JsonDocument::where('document_type', '<>', 'stats-job')->where('document_type', 'LIKE', '%.%')->get();
        foreach ($errors as $error) {
            $error->ref_id = $error->document_type;
            $error->document_type = 'site-errors';
            $error->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // There is no down, only Zuul.
    }
}
