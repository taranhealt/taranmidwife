<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('assignments', function (Blueprint $t) {
            $t->boolean('auto_offered')->default(false);
            $t->timestampTz('offer_expires_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('assignments', function (Blueprint $t) {
            $t->dropColumn(['auto_offered', 'offer_expires_at']);
        });
    }
};
