<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('patchbay.table', 'patchbay_apps'), function (Blueprint $table) {
            // Nullable: applications made with `patchbay:app` belong to no one.
            // Users delete their applications through the model first, so the
            // server hears at once; the cascade is only the backstop.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(config('patchbay.table', 'patchbay_apps'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
