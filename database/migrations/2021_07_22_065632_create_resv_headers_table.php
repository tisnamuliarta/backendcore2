<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResvHeadersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resv_headers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doc_num');
            $table->date('doc_date');
            $table->date('req_date');
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('company_id');
            $table->string('memo')->nullable();
            $table->char('canceled')->default('N');
            $table->char('doc_status');
            $table->char('approval_status');
            $table->unsignedInteger('approval_key')->nullable();
            $table->char('is_confirm')->default('N');
            $table->dateTime('confirm_date')->nullable();
            $table->unsignedInteger('confirm_by')->nullable();
            $table->unsignedInteger('sap_gir_no')->nullable();
            $table->unsignedInteger('sap_trf_no')->nullable();
            $table->unsignedInteger('sap_pr_no')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('req_type');
            $table->string('whs_code');
            $table->string('whs_to')->nullable();
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
        Schema::dropIfExists('resv_headers');
    }
}
