<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResvReqItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resv_req_items', function (Blueprint $table) {
            $table->id();
            $table->string('specification');
            $table->string('item_group');
            $table->string('uom', 20);
            $table->string('supporting_data', 20);
            $table->string('description', 20);
            $table->unsignedBigInteger('created_id');
            $table->unsignedBigInteger('whs_admin');
            $table->string('doc_status', 10)->default('P');
            $table->char('insert_to_sap')->default('N');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('resv_req_items');
    }
}
