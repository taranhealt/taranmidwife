<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_status', 16)->default('UNPAID')->index();
        });
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_payment_status_check CHECK (payment_status IN ('UNPAID','PAID','CANCELLED'))");
        Schema::create('booking_payment_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->constrained('bookings');
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('method', 24)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->index(['booking_id', 'occurred_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('booking_payment_events');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_payment_status_check');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('payment_status'));
    }
};