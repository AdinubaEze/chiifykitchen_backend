<?php

// database/migrations/[timestamp]_create_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon_image')->nullable();
            $table->integer('status')->default(1); // 0 = inactive, 1 = active
            $table->unsignedBigInteger('admin_id');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
};