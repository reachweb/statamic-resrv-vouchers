<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resrv_vouchers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('reservation_id')->unique();
            $table->string('token')->unique();
            $table->string('status')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('used_by_user_id')->nullable();
            $table->text('invalidated_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resrv_vouchers');
    }
};
