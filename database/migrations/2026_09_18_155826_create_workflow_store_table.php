<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The durable records of the Neuron workflow runs, needed to continue a run suspended for a tool approval.
     */
    public function up(): void
    {
        // Keys are compared byte by byte. SQLite, used by the test suite, calls that collation "binary".
        $collation = Schema::getConnection()->getDriverName() === 'sqlite' ? 'binary' : 'ascii_bin';

        Schema::create('workflow_store', function (Blueprint $table) use ($collation) {
            $table->string('partition', 510)->charset('ascii')->collation($collation);
            $table->string('key', 510)->charset('ascii')->collation($collation);
            $table->longText('value')->charset('ascii');
            $table->timestamp('updated_at')->useCurrent();
            $table->primary(['partition', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_store');
    }
};
