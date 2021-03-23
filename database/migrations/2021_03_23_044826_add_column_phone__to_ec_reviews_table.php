<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnPhoneToEcReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ec_reviews', function (Blueprint $table) {
            //
            $table->renameColumn("email", "phone");
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
            $table->dropColumn(['phone']);
        });
    }
}
