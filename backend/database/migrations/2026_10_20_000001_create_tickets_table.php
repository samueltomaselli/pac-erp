<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category');
            $table->text('description');
            $table->string('priority')->default(TicketPriority::Medium->value);
            $table->string('status')->default(TicketStatus::Open->value);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
