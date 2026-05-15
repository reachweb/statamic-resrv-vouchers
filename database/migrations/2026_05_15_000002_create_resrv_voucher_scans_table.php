<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resrv_voucher_scans', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_id')->index();
            $table->string('user_id')->nullable();
            $table->string('action');
            $table->string('result');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resrv_voucher_scans');
    }
};
