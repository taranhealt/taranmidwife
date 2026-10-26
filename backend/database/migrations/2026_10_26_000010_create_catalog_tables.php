<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('services', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('code', 40)->unique();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->text('scope')->nullable();
            $t->text('limitations')->nullable();
            $t->unsignedInteger('price_amount');
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
        Schema::create('areas', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('name', 120)->unique();
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('areas'); Schema::dropIfExists('services'); }
};
