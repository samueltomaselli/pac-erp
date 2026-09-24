<?php

use App\Enums\ProposalItemType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default(ProposalItemType::OneTime->value);
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedTinyInteger('installments')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_items');
    }
};
