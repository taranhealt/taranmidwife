<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    private function values(): array {
        $row = DB::selectOne("select pg_get_constraintdef(oid) as def from pg_constraint where conname = 'users_status_check' and conrelid = 'users'::regclass");
        if (! $row) { throw new \RuntimeException('Constraint users_status_check tidak ditemukan.'); }
        preg_match_all("/'([^']+)'/", $row->def, $m);
        if (! $m[1]) { throw new \RuntimeException('Tidak bisa membaca nilai constraint: ' . $row->def); }
        return $m[1];
    }
    private function replace(array $vals): void {
        $list = implode(',', array_map(fn ($v) => "'" . str_replace("'", "''", $v) . "'", array_values($vals)));
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_status_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ($list))");
    }
    public function up(): void {
        $this->replace(array_values(array_unique(array_merge($this->values(), ['deleted']))));
    }
    public function down(): void {
        $this->replace(array_values(array_diff($this->values(), ['deleted'])));
    }
};
