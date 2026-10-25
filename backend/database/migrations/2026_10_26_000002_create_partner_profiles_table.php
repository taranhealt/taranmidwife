<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('partner_profiles', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('verification_status', 20)->default('DRAFT')->index();
            $t->timestamps();
        });
        DB::statement("ALTER TABLE partner_profiles ADD CONSTRAINT partner_status_check CHECK (verification_status IN ('DRAFT','SUBMITTED','NEEDS_CORRECTION','IN_REVIEW','APPROVED','ACTIVE','REJECTED','EXPIRED','SUSPENDED'))");
    }
    public function down(): void { Schema::dropIfExists('partner_profiles'); }
};
