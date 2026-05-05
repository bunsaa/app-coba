<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Performance index migration.
 *
 * Indexes are added based on the most common query patterns:
 *  - Indikator lookups by unit + active + tim
 *  - Capaian lookups by unit + year (approval/validation counts)
 *  - Lampiran lookups by capaian + bulan
 *  - Penilaian perilaku by unit + periode
 *  - User lookups by role or unit+role
 *  - Tim unit lookups by kode_unit
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── indikators ──────────────────────────────────────────────────────
        // Most queries: WHERE kode_unit=X AND is_active=1
        // Tim-based:    WHERE kode_unit=X AND tim_unit=Y AND is_active=1
        Schema::table('indikators', function (Blueprint $table) {
            $table->index(['kode_unit', 'is_active'], 'idx_indikators_unit_active');
            $table->index(['kode_unit', 'tim_unit', 'is_active'], 'idx_indikators_unit_tim_active');
        });

        // ── tim_units ───────────────────────────────────────────────────────
        // Queried heavily on unit selection dropdown and tim filtering
        Schema::table('tim_units', function (Blueprint $table) {
            $table->index('kode_unit', 'idx_tim_units_kode_unit');
        });

        // ── capaian_indikators ──────────────────────────────────────────────
        // Already has: unique(indikator_id, kode_unit, tahun) + FK(kode_unit)
        // Add: (kode_unit, tahun) for approval count queries that scan by unit+year
        //      Also helps dashboard aggregate queries
        Schema::table('capaian_indikators', function (Blueprint $table) {
            $table->index(['kode_unit', 'tahun'], 'idx_capaian_unit_tahun');
        });

        // ── capaian_lampiran ────────────────────────────────────────────────
        // Already has FK index on capaian_id.
        // Add composite (capaian_id, bulan) so WHERE capaian_id=X AND bulan=Y
        // is an index-only lookup instead of a scan over the full capaian rows.
        Schema::table('capaian_lampiran', function (Blueprint $table) {
            $table->index(['capaian_id', 'bulan'], 'idx_lampiran_capaian_bulan');
        });

        // ── penilaian_perilaku ──────────────────────────────────────────────
        // Dashboard filters by kode_unit + periode; status is used in list queries
        Schema::table('penilaian_perilaku', function (Blueprint $table) {
            $table->index(['kode_unit', 'periode'], 'idx_pp_unit_periode');
            $table->index('status', 'idx_pp_status');
        });

        // ── users ───────────────────────────────────────────────────────────
        // Role-based lookups and unit-scoped user lists
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'idx_users_role');
            $table->index(['kode_unit', 'role'], 'idx_users_unit_role');
        });

        // ── InnoDB buffer pool tuning hint ──────────────────────────────────
        // Increase to ~70% of available RAM in my.ini for best performance:
        //   innodb_buffer_pool_size = 512M   (adjust to your server RAM)
        //   innodb_log_file_size    = 128M
        //   innodb_flush_log_at_trx_commit = 2
        //   query_cache_type        = 0       (disabled in MySQL 8+)
        //   max_connections         = 100
        // These are server-level settings, not applied here automatically.
    }

    public function down(): void
    {
        Schema::table('indikators', function (Blueprint $table) {
            $table->dropIndex('idx_indikators_unit_active');
            $table->dropIndex('idx_indikators_unit_tim_active');
        });

        Schema::table('tim_units', function (Blueprint $table) {
            $table->dropIndex('idx_tim_units_kode_unit');
        });

        Schema::table('capaian_indikators', function (Blueprint $table) {
            $table->dropIndex('idx_capaian_unit_tahun');
        });

        Schema::table('capaian_lampiran', function (Blueprint $table) {
            $table->dropIndex('idx_lampiran_capaian_bulan');
        });

        Schema::table('penilaian_perilaku', function (Blueprint $table) {
            $table->dropIndex('idx_pp_unit_periode');
            $table->dropIndex('idx_pp_status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_unit_role');
        });
    }
};
