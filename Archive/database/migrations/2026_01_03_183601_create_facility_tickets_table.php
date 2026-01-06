<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facility_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Staff who sold the ticket
            $table->foreignId('sale_id')->nullable()->constrained()->onDelete('set null');
            $table->string('ticket_reference')->unique();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->date('ticket_date');
            $table->time('check_in_time')->nullable(); // Optional: when they came
            $table->decimal('amount', 10, 2);
            $table->string('payment_method'); // cash, transfer, card
            $table->string('status')->default('paid'); // paid, refunded
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'ticket_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_tickets');
    }
};
