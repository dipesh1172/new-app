<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesOnApiErrors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('api_errors', function (Blueprint $table) {
            // Get a list of Indexes by their name for the table
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('api_errors');
        
            // If the Index does not exist, then create the index with a specific index name
            if (!array_key_exists('idx_created_at' ,$indexesFound)) { $table->index('created_at','idx_created_at'); }
            if (!array_key_exists('idx_updated_at' ,$indexesFound)) { $table->index('updated_at','idx_updated_at'); }
            if (!array_key_exists('idx_deleted_at' ,$indexesFound)) { $table->index('deleted_at','idx_deleted_at'); }
            if (!array_key_exists('idx_brand_id'   ,$indexesFound)) { $table->index('brand_id'  ,'idx_brand_id'  ); }
            if (!array_key_exists('idx_event_id'   ,$indexesFound)) { $table->index('event_id'  ,'idx_event_id'  ); }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('api_errors', function (Blueprint $table) {
            // Get a list of Indexes by their name for the table
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('api_errors');
        
            // If the index exists, then drop the index by the name of the index, not the column it indexes
            if(array_key_exists('idx_created_at' ,$indexesFound)) { $table->dropIndex('idx_created_at'); }
            if(array_key_exists('idx_updated_at' ,$indexesFound)) { $table->dropIndex('idx_updated_at'); }
            if(array_key_exists('idx_deleted_at' ,$indexesFound)) { $table->dropIndex('idx_deleted_at'); }
            if(array_key_exists('idx_brand_id'   ,$indexesFound)) { $table->dropIndex('idx_brand_id'  ); }
            if(array_key_exists('idx_event_id'   ,$indexesFound)) { $table->dropIndex('idx_event_id'  ); }
        });
    }
}