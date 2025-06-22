<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('cancelled_by_customer')->default(false)->after('status');
            $table->timestamp('customer_cancelled_at')->nullable()->after('cancelled_by_customer');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cancelled_by_customer');
            $table->dropColumn('customer_cancelled_at');
        });
    }
};