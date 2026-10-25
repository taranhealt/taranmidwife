<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('audit_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 60)->index();
            $t->string('entity_type', 60)->nullable();
            $t->string('entity_id', 40)->nullable();
            $t->string('outcome', 20);
            $t->timestampTz('occurred_at')->useCurrent();
        });
    }
    public function down(): void { Schema::dropIfExists('audit_events'); }
};
