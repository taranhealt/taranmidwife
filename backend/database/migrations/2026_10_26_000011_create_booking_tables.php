<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('bookings', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('code', 20)->unique();
            $t->foreignId('patient_id')->constrained('users');
            $t->foreignUlid('service_id')->constrained('services');
            $t->foreignUlid('area_id')->constrained('areas');
            $t->string('status', 20)->default('REQUESTED')->index();
            $t->timestampTz('scheduled_start');
            $t->unsignedInteger('price_amount');
            $t->text('address_line');
            $t->string('landmark', 100)->nullable();
            $t->text('contact_phone');
            $t->string('idempotency_key', 64);
            $t->string('cancel_reason', 200)->nullable();
            $t->timestamps();
            $t->unique(['patient_id', 'idempotency_key']);
        });
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('REQUESTED','NEEDS_INFO','SCREENING','ESCALATED','WAITING_MIDWIFE','OFFERED','CONFIRMED','EN_ROUTE','IN_PROGRESS','COMPLETED','CANCELLED','UNFULFILLED','COMPLAINT_REVIEW'))");

        Schema::create('assignments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('booking_id')->constrained('bookings');
            $t->foreignId('partner_id')->constrained('users');
            $t->timestampTz('scheduled_start');
            $t->boolean('active')->default(true);
            $t->timestampTz('accepted_at')->nullable();
            $t->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX assignments_partner_slot_active ON assignments (partner_id, scheduled_start) WHERE active');
        DB::statement('CREATE UNIQUE INDEX assignments_booking_active ON assignments (booking_id) WHERE active');

        Schema::create('booking_status_histories', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('booking_id')->constrained('bookings');
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason', 200)->nullable();
            $t->timestampTz('occurred_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('bookings');
    }
};
