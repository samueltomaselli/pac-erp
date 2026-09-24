<?php

use App\Enums\ProposalItemType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default(ProposalItemType::Recurring->value);
            $table->unsignedInteger('default_quantity')->default(1);
            $table->unsignedBigInteger('default_unit_amount_cents');
            $table->boolean('allows_installments')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_catalog_items');
    }
};
