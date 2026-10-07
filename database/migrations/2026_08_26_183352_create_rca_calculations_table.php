<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rca_calculations', function (Blueprint $table): void {
            $table->id();
            $table->string('insurer');
            $table->unsignedBigInteger('offer_id')->nullable();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->json('request_payload');
            $table->json('offer_response')->nullable();
            $table->json('policy_response')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rca_calculations');
    }
};
