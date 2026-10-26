<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('bookings', function (Blueprint $t) {
            $t->text('patient_lat')->nullable();
            $t->text('patient_lng')->nullable();
        });
        Schema::table('partner_profiles', function (Blueprint $t) {
            $t->decimal('base_lat', 9, 6)->nullable();
            $t->decimal('base_lng', 9, 6)->nullable();
            $t->unsignedSmallInteger('service_radius_km')->nullable();
        });
        DB::statement('ALTER TABLE partner_profiles ADD CONSTRAINT partner_base_geo_check CHECK ((base_lat IS NULL AND base_lng IS NULL AND service_radius_km IS NULL) OR (base_lat IS NOT NULL AND base_lng IS NOT NULL AND service_radius_km IS NOT NULL AND base_lat BETWEEN -90 AND 90 AND base_lng BETWEEN -180 AND 180 AND service_radius_km BETWEEN 1 AND 100))');
    }
    public function down(): void {
        DB::statement('ALTER TABLE partner_profiles DROP CONSTRAINT IF EXISTS partner_base_geo_check');
        Schema::table('partner_profiles', function (Blueprint $t) {
            $t->dropColumn(['base_lat', 'base_lng', 'service_radius_km']);
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropColumn(['patient_lat', 'patient_lng']);
        });
    }
};
