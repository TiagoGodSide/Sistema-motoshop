<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_coordination', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });

        DB::table('financial_coordination')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_coordination');
    }
};
