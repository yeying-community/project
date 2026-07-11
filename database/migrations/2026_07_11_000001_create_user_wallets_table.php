<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userid')->index();
            $table->string('chain', 32)->default('eip155');
            $table->string('chain_id', 64)->default('1');
            $table->string('address', 42);
            $table->string('address_normalized', 42);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->unique(['chain', 'chain_id', 'address_normalized'], 'user_wallets_identity_unique');
            $table->foreign('userid')->references('userid')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wallets');
    }
};
