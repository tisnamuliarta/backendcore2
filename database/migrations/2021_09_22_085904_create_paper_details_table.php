<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaperDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 50);
            $table->string('name');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('paper_details', function (Blueprint $table) {
            $table->id();
            $table->string('name_title', 50)->nullable();
            $table->string('name', 200)->nullable();
            $table->string('position', 100)->nullable();
            $table->float('body_weight')->nullable();
            $table->string('departing_city')->nullable();
            $table->date('arrival_date')->nullable();
            $table->string('arrival_flight_no')->nullable();
            $table->time('arrival_time')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('departure_flight_no')->nullable();
            $table->time('departure_time')->nullable();
            $table->string('destination_city', 100)->nullable();
            $table->string('transport_to', 100)->nullable();
            $table->string('transport_from', 100)->nullable();
            $table->string('notes')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('id_card', 100)->nullable();
            $table->string('employee_type', 100)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('seat_no', 200)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->string('resv_for', 100)->nullable();
            $table->string('travel_purpose', 100)->nullable();
            $table->string('reason_purpose', 200)->nullable();
            $table->string('cost_cover', 100)->nullable();
            $table->string('work_location', 100)->nullable();
            $table->tinyInteger('total_seat')->nullable();
            $table->date('request_date')->nullable();
            $table->unsignedBigInteger('flight_origin')->nullable();
            $table->unsignedBigInteger('flight_destination')->nullable();
            $table->string('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('paper_details');
    }
}
