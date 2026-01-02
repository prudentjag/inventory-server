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
        // Rename pitches to facilities
        Schema::rename('pitches', 'facilities');
        
        // Add type column to facilities
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('type')->default('pitch')->after('name'); // pitch, event_hall, court, etc.
        });

        // Rename pitch_bookings to facility_bookings
        Schema::rename('pitch_bookings', 'facility_bookings');
        
        // Rename pitch_id to facility_id in facility_bookings
        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->renameColumn('pitch_id', 'facility_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename facility_id back to pitch_id
        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->renameColumn('facility_id', 'pitch_id');
        });

        // Rename facility_bookings back to pitch_bookings
        Schema::rename('facility_bookings', 'pitch_bookings');

        // Remove type column
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        // Rename facilities back to pitches
        Schema::rename('facilities', 'pitches');
    }
};
