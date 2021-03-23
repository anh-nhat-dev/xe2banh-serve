<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnEmailToEcReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ec_reviews', function (Blueprint $table) {
            $table->string("full_name", 100);
            $table->string("email", 100);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ec_reviews', function (Blueprint $table) {
            $table->dropColumn(['full_name', 'email']);
        });
    }
}
