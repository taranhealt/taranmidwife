<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('patient_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['patient_id', 'service_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('patient_favorites'); }
};
