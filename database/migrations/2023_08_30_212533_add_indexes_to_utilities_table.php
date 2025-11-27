<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToUtilitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('utilities', function (Blueprint $table) {
            // Get a list of Indexes by their name for the table
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('utilities');
        
            // If the Index does not exist, then create the index with a specific index name
            if (!array_key_exists('idx_created_at', $indexesFound)) { $table->index('created_at','idx_created_at'); }
            if (!array_key_exists('idx_updated_at', $indexesFound)) { $table->index('updated_at','idx_updated_at'); }
            if (!array_key_exists('idx_deleted_at', $indexesFound)) { $table->index('deleted_at','idx_deleted_at'); }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('utilities', function (Blueprint $table) {
            // Get a list of Indexes by their name for the table
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('utilities');
        
            // If the index exists, then drop the index by the name of the index, not the column it indexes
            if(array_key_exists('idx_created_at', $indexesFound)) { $table->dropIndex('idx_created_at'); }
            if(array_key_exists('idx_updated_at', $indexesFound)) { $table->dropIndex('idx_updated_at'); }
            if(array_key_exists('idx_deleted_at', $indexesFound)) { $table->dropIndex('idx_deleted_at'); }
        });
    }
}
