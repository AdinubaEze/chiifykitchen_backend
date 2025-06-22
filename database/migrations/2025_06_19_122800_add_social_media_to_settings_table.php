<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; 

return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            // Add the new column as JSON type
            $table->json('social_media')->nullable()->after('general_settings');
        });

        // Update existing records with default values
        DB::table('settings')->update([
            'social_media' => json_encode([
                'facebook' => null,
                'linkedin' => null,
                'tiktok' => null,
                'instagram' => null,
                'youtube' => null,
                'x' => null,
                'thread' => null,
                'snapchat' => null
            ])
        ]);
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('social_media');
        });
    }
};