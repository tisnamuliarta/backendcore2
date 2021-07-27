<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePapersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("master_paper_id")->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string("paper_no")->nullable();
            $table->string("user_name")->nullable();
            $table->text("address")->nullable();
            $table->string("no_hp")->nullable();
            $table->string("ktp")->nullable();
            $table->string("id_card", 50)->nullable();
            $table->string("department", 50)->nullable();
            $table->string("company", 100)->nullable();
            $table->string("occupation", 50)->nullable();
            $table->string("payment")->nullable();
            $table->string("name_boss", 100)->nullable();
            $table->string("position_boss", 50)->nullable();
            $table->string("nik_boss", 50)->nullable();
            $table->date("paper_date")->nullable();
            $table->text("reason")->nullable();
            $table->date("date_out")->nullable();
            $table->date("date_in")->nullable();
            $table->string("period_stay")->nullable();
            $table->string("destination")->nullable();
            $table->string("transportation")->nullable();
            $table->string("route")->nullable();
            $table->dateTime("print_date")->nullable();
            $table->unsignedBigInteger("created_by")->nullable();
            $table->string("deleted", 1)->default("N");
            $table->string("for_self", 5)->default("Yes");
            $table->string('str_url')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('created_name')->nullable();
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
        Schema::dropIfExists('papers');
    }
}
