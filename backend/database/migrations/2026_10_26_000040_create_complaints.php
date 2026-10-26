<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('complaints', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('booking_id')->constrained('bookings');
            $t->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('category', 30);
            $t->text('description');
            $t->string('status', 20)->default('OPEN');
            $t->boolean('safety_flag')->default(false);
            $t->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('resolution_note')->nullable();
            $t->timestampTz('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
            $t->index('reporter_id');
        });
        DB::statement("ALTER TABLE complaints ADD CONSTRAINT complaints_category_check CHECK (category IN ('SERVICE_QUALITY','PARTNER_CONDUCT','SCHEDULING','PRICE_OR_PAYMENT','PRIVACY','SAFETY','OTHER'))");
        DB::statement("ALTER TABLE complaints ADD CONSTRAINT complaints_status_check CHECK (status IN ('OPEN','IN_REVIEW','RESOLVED','CLOSED'))");
        Schema::create('complaint_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('occurred_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('complaint_events');
        Schema::dropIfExists('complaints');
    }
};
