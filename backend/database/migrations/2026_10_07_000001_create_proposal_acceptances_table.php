<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document', 14);
            $table->string('email');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_acceptances');
    }
};
