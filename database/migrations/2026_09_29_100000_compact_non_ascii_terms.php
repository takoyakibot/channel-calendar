<?php

use App\Support\TermNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Stored terms move to TermNormalizer::key() form (non-ASCII without spaces); rows that collide are dropped. */
    public function up(): void
    {
        foreach (['tag_aliases' => 'alias', 'ignored_terms' => 'term', 'unmatched_terms' => 'term', 'channel_aliases' => 'alias'] as $table => $column) {
            foreach (DB::table($table)->orderBy('id')->get(['id', $column]) as $row) {
                $key = TermNormalizer::key((string) $row->{$column});
                if ($key === $row->{$column}) {
                    continue;
                }
                if ($key === '' || DB::table($table)->where($column, $key)->where('id', '!=', $row->id)->exists()) {
                    DB::table($table)->where('id', $row->id)->delete();
                } else {
                    DB::table($table)->where('id', $row->id)->update([$column => $key]);
                }
            }
        }
    }

    public function down(): void
    {
        // Spacing cannot be restored; nothing to undo.
    }
};
