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
            // ponytail: the cascade skips model events, so a deleted user's apps
            // keep serving until the server's next reconcile (reload.reconcile_every).
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
