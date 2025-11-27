<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToStatsProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stats_product', function (Blueprint $table) {
            // Get a list of Indexes by their name for the table
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('stats_product');
        
            // If the Index does not exist, then create the index
            if (!array_key_exists('idx_created_at',           $indexesFound)) { $table->index('created_at',           'idx_created_at');            }
            if (!array_key_exists('idx_updated_at',           $indexesFound)) { $table->index('updated_at',           'idx_updated_at');            }
            if (!array_key_exists('idx_deleted_at',           $indexesFound)) { $table->index('deleted_at',           'idx_deleted_at');            }
            if (!array_key_exists('idx_result',               $indexesFound)) { $table->index('result',               'idx_result');                }
            if (!array_key_exists('idx_brand_id',             $indexesFound)) { $table->index('brand_id',             'idx_brand_id');              }
            if (!array_key_exists('idx_vendor_id',            $indexesFound)) { $table->index('vendor_id',            'idx_vendor_id');             }
            if (!array_key_exists('idx_office_id',            $indexesFound)) { $table->index('office_id',            'idx_office_id');             }
            if (!array_key_exists('idx_utility_id',           $indexesFound)) { $table->index('utility_id',           'idx_utility_id');            }
            if (!array_key_exists('idx_product_id',           $indexesFound)) { $table->index('product_id',           'idx_product_id');            }
            if (!array_key_exists('idx_eztpv_id',             $indexesFound)) { $table->index('eztpv_id',             'idx_eztpv_id');              }
            if (!array_key_exists('idx_disposition_id',       $indexesFound)) { $table->index('disposition_id',       'idx_disposition_id');        }
            if (!array_key_exists('idx_service_state',        $indexesFound)) { $table->index('service_state',        'idx_service_state');         }
            if (!array_key_exists('idx_channel_id',           $indexesFound)) { $table->index('channel_id',           'idx_channel_id');            }
            if (!array_key_exists('idx_market_id',            $indexesFound)) { $table->index('market_id',            'idx_market_id');             }
            if (!array_key_exists('idx_language_id',          $indexesFound)) { $table->index('language_id',          'idx_language_id');           }
            if (!array_key_exists('idx_commodity_id',         $indexesFound)) { $table->index('commodity_id',         'idx_commodity_id');          }
            if (!array_key_exists('idx_sales_agent_id',       $indexesFound)) { $table->index('sales_agent_id',       'idx_sales_agent_id');        }
            if (!array_key_exists('idx_disposition_reason',   $indexesFound)) { $table->index('disposition_reason',   'idx_disposition_reason');    }
            if (!array_key_exists('idx_event_created_at',     $indexesFound)) { $table->index('event_created_at',     'idx_event_created_at');      }
            if (!array_key_exists('idx_stats_product_type_id',$indexesFound)) { $table->index('stats_product_type_id','idx_stats_product_type_id'); }
            if (!array_key_exists('idx_service_zip',          $indexesFound)) { $table->index('service_zip','idx_service_zip');                     }
            if (!array_key_exists('idx_service_county',       $indexesFound)) { $table->index('service_county','idx_service_county');               }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stats_product', function (Blueprint $table) {
            // Get a list of Indexes by their name for the table
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('stats_product');
        
            // If the index exists, then drop the index by the name of the index, not the column it indexes
            if (array_key_exists('idx_created_at',           $indexesFound)) { $table->dropIndex('idx_created_at');            }
            if (array_key_exists('idx_updated_at',           $indexesFound)) { $table->dropIndex('idx_updated_at');            }
            if (array_key_exists('idx_deleted_at',           $indexesFound)) { $table->dropIndex('idx_deleted_at');            }
            if (array_key_exists('idx_result',               $indexesFound)) { $table->dropIndex('idx_result');                }
            if (array_key_exists('idx_brand_id',             $indexesFound)) { $table->dropIndex('idx_brand_id');              }
            if (array_key_exists('idx_vendor_id',            $indexesFound)) { $table->dropIndex('idx_vendor_id');             }
            if (array_key_exists('idx_office_id',            $indexesFound)) { $table->dropIndex('idx_office_id');             }
            if (array_key_exists('idx_utility_id',           $indexesFound)) { $table->dropIndex('idx_utility_id');            }
            if (array_key_exists('idx_product_id',           $indexesFound)) { $table->dropIndex('idx_product_id');            }
            if (array_key_exists('idx_eztpv_id',             $indexesFound)) { $table->dropIndex('idx_eztpv_id');              }
            if (array_key_exists('idx_disposition_id',       $indexesFound)) { $table->dropIndex('idx_disposition_id');        }
            if (array_key_exists('idx_service_state',        $indexesFound)) { $table->dropIndex('idx_service_state');         }
            if (array_key_exists('idx_channel_id',           $indexesFound)) { $table->dropIndex('idx_channel_id');            }
            if (array_key_exists('idx_market_id',            $indexesFound)) { $table->dropIndex('idx_market_id');             }
            if (array_key_exists('idx_commodity_id',         $indexesFound)) { $table->dropIndex('idx_commodity_id');          }
            if (array_key_exists('idx_sales_agent_id',       $indexesFound)) { $table->dropIndex('idx_sales_agent_id');        }
            if (array_key_exists('idx_disposition_reason',   $indexesFound)) { $table->dropIndex('idx_disposition_reason');    }
            if (array_key_exists('idx_event_created_at',     $indexesFound)) { $table->dropIndex('idx_event_created_at');      }
            if (array_key_exists('idx_stats_product_type_id',$indexesFound)) { $table->dropIndex('idx_stats_product_type_id'); }
            if (array_key_exists('idx_service_zip',          $indexesFound)) { $table->dropIndex('idx_service_zip');           }
            if (array_key_exists('idx_service_county',       $indexesFound)) { $table->dropIndex('idx_service_county');        }
        });
    }
}
