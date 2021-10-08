<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnToPapersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->string('flight_no', 100)->nullable();
            $table->string('host_company')->nullable();
            $table->string('visitor_company')->nullable();
            $table->string('company_officer')->nullable();
            $table->string('visitor_officer')->nullable();
            $table->string('visitor_address')->nullable();
            $table->string('company_email')->nullable();
            $table->string('visitor_email')->nullable();
            $table->json('plan_visit_area')->nullable();
            $table->string('purpose_visit')->nullable();
            $table->smallInteger('total_guest')->nullable();
            $table->json('facilities')->nullable();
            $table->string('paper_place')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('papers', function (Blueprint $table) {
            //
        });
    }
}
