<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('donor_verifications')
            && ! Schema::hasColumn('donor_verifications', 'document_back_path')) {
            Schema::table('donor_verifications', function (Blueprint $table): void {
                $table->string('document_back_path', 255)->nullable()->after('document_path');
            });
        }
    }

    public function down(): void
    {
        // Keep this column on rollback: mobile deployments may have created it
        // before this Laravel migration, and it can contain donor document paths.
    }
};
