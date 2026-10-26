<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('partner_profiles', function (Blueprint $t) {
            $t->boolean('accepting_requests')->default(true);
            $t->timestampTz('last_offered_at')->nullable();
        });
        Schema::create('partner_services', function (Blueprint $t) {
            $t->foreignUlid('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $t->foreignUlid('service_id')->constrained('services');
            $t->primary(['partner_profile_id', 'service_id']);
        });
        Schema::create('partner_areas', function (Blueprint $t) {
            $t->foreignUlid('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $t->foreignUlid('area_id')->constrained('areas');
            $t->primary(['partner_profile_id', 'area_id']);
        });
        Schema::create('partner_availabilities', function (Blueprint $t) {
            $t->foreignUlid('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $t->unsignedSmallInteger('weekday');
            $t->unsignedSmallInteger('hour');
            $t->primary(['partner_profile_id', 'weekday', 'hour']);
        });
        DB::statement('ALTER TABLE partner_availabilities ADD CONSTRAINT partner_availability_check CHECK (weekday BETWEEN 0 AND 6 AND hour BETWEEN 0 AND 23)');
    }
    public function down(): void {
        Schema::dropIfExists('partner_availabilities');
        Schema::dropIfExists('partner_areas');
        Schema::dropIfExists('partner_services');
        Schema::table('partner_profiles', function (Blueprint $t) {
            $t->dropColumn(['accepting_requests', 'last_offered_at']);
        });
    }
};
