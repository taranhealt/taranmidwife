<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('partner_profiles', function (Blueprint $t) {
            $t->text('nik')->nullable();
            $t->string('sip_number', 60)->nullable();
            $t->string('sip_issuer', 120)->nullable();
            $t->date('sip_expires_on')->nullable();
            $t->string('practice_area', 120)->nullable();
            $t->timestampTz('submitted_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('review_reason', 200)->nullable();
            $t->timestampTz('reviewed_at')->nullable();
        });
        Schema::create('partner_documents', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $t->string('type', 20);
            $t->string('storage_key', 200);
            $t->string('mime', 60);
            $t->unsignedInteger('size_bytes');
            $t->string('sha256', 64);
            $t->timestamps();
            $t->unique(['partner_profile_id', 'type']);
        });
        DB::statement("ALTER TABLE partner_documents ADD CONSTRAINT partner_documents_type_check CHECK (type IN ('SIP','STR','IDENTITY','FACE_PHOTO'))");
        Schema::create('partner_verification_items', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $t->string('item_type', 30);
            $t->string('result', 20);
            $t->string('method', 120);
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestampTz('checked_at');
            $t->unique(['partner_profile_id', 'item_type']);
        });
        DB::statement("ALTER TABLE partner_verification_items ADD CONSTRAINT partner_items_type_check CHECK (item_type IN ('IDENTITY','PROFESSIONAL_STATUS','IHS','SIP','FACE_PHOTO'))");
        DB::statement("ALTER TABLE partner_verification_items ADD CONSTRAINT partner_items_result_check CHECK (result IN ('verified','unverified_limited','failed'))");
        Schema::create('partner_verification_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('partner_profile_id')->constrained('partner_profiles')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason', 200)->nullable();
            $t->timestampTz('occurred_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('partner_verification_events');
        Schema::dropIfExists('partner_verification_items');
        Schema::dropIfExists('partner_documents');
        Schema::table('partner_profiles', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reviewed_by');
            $t->dropColumn(['nik', 'sip_number', 'sip_issuer', 'sip_expires_on', 'practice_area', 'submitted_at', 'review_reason', 'reviewed_at']);
        });
    }
};
