<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * These columns hold TermNormalizer::key() values, which PHP already folded
 * and compares byte for byte. utf8mb4_unicode_ci additionally folded accents,
 * kana and variation selectors, so the DB and PHP disagreed:
 *  - unmatched_terms: "holocure" and "holoçure" collided on the unique key and
 *    every rebuild (streams:fetch, retagAll) died;
 *  - tag_aliases / ignored_terms: "マシュマロ" counted as already classified
 *    because "ましゅまろ" existed, yet PHP never matched it, so the term could
 *    neither be tagged nor classified.
 * Existing rows are unique under unicode_ci, so they stay unique under bin.
 */
return new class extends Migration
{
    private const COLUMNS = [
        ['unmatched_terms', 'term'],
        ['ignored_terms', 'term'],
        ['tag_aliases', 'alias'],
        ['channel_aliases', 'alias'],
    ];

    public function up(): void
    {
        $this->setCollation('utf8mb4_bin');
    }

    public function down(): void
    {
        $this->setCollation('utf8mb4_unicode_ci');
    }

    private function setCollation(string $collation): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach (self::COLUMNS as [$table, $column]) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(80) CHARACTER SET utf8mb4 COLLATE {$collation} NOT NULL");
        }
    }
};
