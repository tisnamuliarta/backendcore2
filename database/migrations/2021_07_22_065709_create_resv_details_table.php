<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResvDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resv_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doc_num');
            $table->unsignedInteger('line_num');
            $table->unsignedBigInteger('item_code');
            $table->string('item_name');
            $table->string('whs_code');
            $table->string('uom_code');
            $table->string('uom_name')->nullable();
            $table->decimal('req_qty', 20, 4);
            $table->string('req_note')->nullable();
            $table->date('req_date');
            $table->unsignedBigInteger('other_resv_no')->nullable();
            $table->string('req_type')->nullable();
            $table->decimal('qty_ready_issue', 20, 4)->nullable();
            $table->string('line_status', 20)->nullable();
            $table->unsignedInteger('sap_gir_no')->nullable();
            $table->unsignedInteger('sap_trf_no')->nullable();
            $table->unsignedInteger('sap_pr_no')->nullable();
            $table->unsignedInteger('oigr_docnum')->nullable();
            $table->string('invnt_item')->nullable();
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
        Schema::dropIfExists('resv_details');
    }
}
