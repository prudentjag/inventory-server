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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->date('report_date');
            $table->decimal('total_sales_amount', 12, 2)->default(0);
            $table->integer('total_items_sold')->default(0);
            $table->integer('total_stock_received')->default(0);
            $table->integer('total_damages')->default(0);
            $table->text('remark')->nullable();
            $table->enum('status', ['open', 'closed'])->default('closed');
            $table->timestamps();

            // One report per user/unit/day
            $table->unique(['user_id', 'unit_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
