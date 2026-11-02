<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('booking_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique('booking_id');
            $table->index(['partner_id', 'rating']);
        });
        DB::statement('ALTER TABLE booking_reviews ADD CONSTRAINT booking_reviews_rating_check CHECK (rating BETWEEN 1 AND 5)');
    }
    public function down(): void { Schema::dropIfExists('booking_reviews'); }
};
