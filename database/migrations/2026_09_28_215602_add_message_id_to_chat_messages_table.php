<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Neuron message store identifies every message by a message_id unique within its thread,
     * backfilled from the identity previously stored in the meta column.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('message_id', 64)->nullable()->after('thread_id');
        });

        $seen = [];

        DB::table('chat_messages')->orderBy('id')->chunkById(500, function (Collection $rows) use (&$seen) {
            foreach ($rows as $row) {
                $messageId = json_decode($row->meta ?? '{}', true)['__id'] ?? "legacy_{$row->id}";

                // Rows are never deleted: a repeated identity within a thread gets a suffix.
                if (isset($seen[$row->thread_id][$messageId])) {
                    $messageId = "{$messageId}_{$row->id}";
                }

                $seen[$row->thread_id][$messageId] = true;

                DB::table('chat_messages')->where('id', $row->id)->update(['message_id' => $messageId]);
            }
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('message_id', 64)->nullable(false)->change();
            $table->unique(['thread_id', 'message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropUnique(['thread_id', 'message_id']);
            $table->dropColumn('message_id');
        });
    }
};
