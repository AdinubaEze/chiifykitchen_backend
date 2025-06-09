<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->json('payment_gateways')->nullable();
            $table->enum('transaction_mode', ['test', 'live'])->default('test');
            $table->json('company_info')->nullable();
            $table->json('notifications')->nullable();
            $table->json('general_settings')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('settings');
    }
};