<?php
// database/migrations/[timestamp]_create_activity_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action'); // create, update, delete
            $table->string('model'); // Model name (e.g., Category)
            $table->unsignedBigInteger('model_id');
            $table->text('changes')->nullable(); // JSON changes
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_logs');
    }
};