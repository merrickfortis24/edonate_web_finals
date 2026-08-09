<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VERIFICATIONS_TABLE = 'donor_verifications';
    private const DONORS_TABLE = 'donors';

    public function up(): void
    {
        if (! Schema::hasTable(self::VERIFICATIONS_TABLE)) {
            Schema::create(self::VERIFICATIONS_TABLE, function (Blueprint $table): void {
                $table->increments('verification_id');
                $table->integer('donor_id');
                $table->enum('document_type', [
                    'national_id',
                    'school_id',
                    'company_id',
                    'barangay_certificate',
                    'government_id',
                    'other',
                ]);
                $table->string('document_path', 255);
                $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->integer('reviewed_by_admin_id')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->foreign('donor_id')
                    ->references('donor_id')
                    ->on('donors')
                    ->cascadeOnDelete();

                $table->foreign('reviewed_by_admin_id')
                    ->references('admin_id')
                    ->on('admins')
                    ->nullOnDelete();

                $table->index(['status', 'created_at']);
                $table->index(['donor_id', 'status']);
            });
        } else {
            $this->addMissingVerificationColumns();
        }

        $this->ensureDonorVerificationStatusColumn();
    }

    public function down(): void
    {
        if (Schema::hasTable(self::DONORS_TABLE) && Schema::hasColumn(self::DONORS_TABLE, 'verification_status')) {
            Schema::table(self::DONORS_TABLE, function (Blueprint $table): void {
                $table->dropColumn('verification_status');
            });
        }

        Schema::dropIfExists(self::VERIFICATIONS_TABLE);
    }

    private function addMissingVerificationColumns(): void
    {
        Schema::table(self::VERIFICATIONS_TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::VERIFICATIONS_TABLE, 'donor_id')) {
                $table->unsignedInteger('donor_id')->after('verification_id');
            }

            if (! Schema::hasColumn(self::VERIFICATIONS_TABLE, 'document_type')) {
                $table->enum('document_type', [
                    'national_id',
                    'school_id',
                    'company_id',
                    'barangay_certificate',
                    'government_id',
                    'other',
                ])->after('donor_id');
            }

            if (! Schema::hasColumn(self::VERIFICATIONS_TABLE, 'document_path')) {
                $table->string('document_path', 255)->after('document_type');
            }

            if (! Schema::hasColumn(self::VERIFICATIONS_TABLE, 'status')) {
                $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending')->after('document_path');
            }

            if (! Schema::hasColumn(self::VERIFICATIONS_TABLE, 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn(self::VERIFICATIONS_TABLE, 'reviewed_by_admin_id')) {
                $table->unsignedInteger('reviewed_by_admin_id')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn(self::VERIFICATIONS_TABLE, 'reviewed_at')) {
                $table->dateTime('reviewed_at')->nullable()->after('reviewed_by_admin_id');
            }

            if (! Schema::hasColumn(self::VERIFICATIONS_TABLE, 'created_at')) {
                $table->timestamp('created_at')->useCurrent()->after('reviewed_at');
            }

            if (! Schema::hasColumn(self::VERIFICATIONS_TABLE, 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->after('created_at');
            }
        });
    }

    private function ensureDonorVerificationStatusColumn(): void
    {
        if (! Schema::hasTable(self::DONORS_TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::DONORS_TABLE, 'verification_status')) {
            Schema::table(self::DONORS_TABLE, function (Blueprint $table): void {
                $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])
                    ->default('unverified')
                    ->after('date_registered');
            });

            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne(
            'SELECT DATA_TYPE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [self::DONORS_TABLE, 'verification_status']
        );

        if (! $column || strtolower((string) $column->DATA_TYPE) !== 'enum') {
            return;
        }

        $values = $this->enumValues((string) $column->COLUMN_TYPE);
        foreach (['unverified', 'pending', 'verified', 'rejected'] as $requiredValue) {
            if (! in_array($requiredValue, $values, true)) {
                $values[] = $requiredValue;
            }
        }

        $enumSql = implode(',', array_map(
            fn (string $value): string => "'" . str_replace("'", "''", $value) . "'",
            $values
        ));

        DB::statement(
            "ALTER TABLE `" . self::DONORS_TABLE . "` MODIFY `verification_status` ENUM({$enumSql}) NOT NULL DEFAULT 'unverified'"
        );
    }

    /**
     * @return array<int, string>
     */
    private function enumValues(string $columnType): array
    {
        if (! str_starts_with(strtolower($columnType), 'enum(')) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (string $value): string => strtolower(str_replace("''", "'", $value)),
                str_getcsv(substr($columnType, 5, -1), ',', "'")
            ),
            fn (string $value): bool => $value !== ''
        ));
    }
};
