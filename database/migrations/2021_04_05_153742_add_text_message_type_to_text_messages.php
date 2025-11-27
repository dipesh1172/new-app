<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTextMessageTypeToTextMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('text_messages', function (Blueprint $table) {
            $table->bigInteger('text_message_type_id')->default(1);
            $table->index(['brand_id', 'text_message_type_id']);
        });

        DB::table('text_messages')
            ->where('content', 'LIKE', 'Verification%')
            ->update([
                'text_message_type_id' => 2
            ]);

        DB::table('text_messages')
            ->where('content', 'LIKE', '%is ready to sign')
            ->orWhere('content', 'LIKE', '%está listo para firmar')
            ->update([
                'text_message_type_id' => 3
            ]);

        DB::table('text_messages')
            ->where('content', 'LIKE', '%signup by visiting the link above.')
            ->orWhere('content', 'LIKE', '%click to complete your verification if needed.')
            ->orWhere('content', 'LIKE', '%para completar su verificación si es necesario.')
            ->update([
                'text_message_type_id' => 4
            ]);

        DB::table('text_messages')
            ->where('content', 'LIKE', '%download your attachments%')
            ->orWhere('content', 'LIKE', '%Your contract from%')
            ->update([
                'text_message_type_id' => 5
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('text_messages', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'text_message_type_id']);
            $table->dropColumn('text_message_type_id');
        });
    }
}
