<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Creates a complete, repeatable demo dataset without touching admin/security
 * tables. The seeder intentionally uses the query builder so model observers
 * cannot mirror the records to Firebase or create audit/admin notifications.
 */
class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    /** @var array<int, string> */
    private const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /** The imported Phase 10/11 tables do not have AUTO_INCREMENT keys. */
    private const MANUAL_ID_TABLES = [
        'donation_events' => 'event_id',
        'facilities' => 'facility_id',
        'facility_blood_inventory' => 'inventory_id',
        'facility_blood_inventory_logs' => 'inventory_log_id',
        'blood_requests' => 'request_id',
        'blood_request_donors' => 'id',
        'donor_screening_answers' => 'answer_id',
    ];

    /** @var array<int, string> */
    private const PROTECTED_TABLES = [
        'admins',
        'admin_notifications',
        'admin_security_settings',
        'audit_logs',
        'sessions',
        'migrations',
    ];

    /** @var array<int, int> */
    private array $bloodTypeIds = [];

    /** @var array<int, int> */
    private array $locationIds = [];

    /** @var array<int, int> */
    private array $donorIds = [];

    /** @var array<int, string> */
    private array $donorEmails = [];

    /** @var array<int, int> */
    private array $eventIds = [];

    /** @var array<int, int> */
    private array $facilityIds = [];

    /** @var array<int, int> */
    private array $requestIds = [];

    /** @var array<int, int> */
    private array $screeningQuestionIds = [];

    /** @var array<int, int> */
    private array $eligibilityQuestionIds = [];

    private ?string $passwordHash = null;

    private Carbon $today;

    /** @var array<string, mixed> */
    private array $questionDefinitions = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoDataSeeder is restricted to the local or testing environment.');
        }

        $this->today = Carbon::today();
        $protectedBefore = $this->protectedSnapshot();

        // The supplied dump predates several runtime fields. This only adds
        // missing non-protected compatibility structures; it never removes or
        // rewrites existing application data.
        $this->ensureRuntimeCompatibility();

        DB::transaction(function () use ($protectedBefore): void {
            $this->seedBloodTypes();
            $this->seedLocations();
            $this->seedQuestions();
            $this->seedDonors();
            $this->seedFacilities();
            $this->seedEvents();
            $this->seedAppointmentsAndDonations();
            $this->seedEligibility();
            $this->seedVerifications();
            $this->seedFacilityInventory();
            $this->seedBloodRequests();
            $this->seedNotifications();

            $this->assertProtectedSnapshot($protectedBefore);
        });

        $this->assertProtectedSnapshot($protectedBefore);
        $this->writeDemoPlaceholderFiles();
        $this->printSummary();
    }

    /**
     * Add only the non-protected fields that the current application reads.
     * This is needed because the provided SQL dump has an older Phase 7–11
     * shape and no screening_questions table, while the current controllers
     * and services query those runtime fields directly.
     */
    private function ensureRuntimeCompatibility(): void
    {
        $requiredTables = [
            'blood_types',
            'locations',
            'donors',
            'donor_authentication',
            'donor_verifications',
            'eligibility_status',
            'eligibility_submissions',
            'eligibility_answers',
            'donor_screening_answers',
            'donation_events',
            'appointments',
            'donation_records',
            'facilities',
            'facility_blood_inventory',
            'facility_blood_inventory_logs',
            'blood_requests',
            'blood_request_donors',
            'notifications',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required application table [{$table}] is missing.");
            }
        }

        $this->addColumnIfMissing('locations', 'barangay_code', function (Blueprint $table): void {
            $table->string('barangay_code', 30)->nullable();
        });

        $this->addColumnIfMissing('donors', 'blood_type_status', function (Blueprint $table): void {
            $table->string('blood_type_status', 30)->default('not_yet_determined');
        });
        $this->addColumnIfMissing('donors', 'blood_type_verified_by_admin_id', function (Blueprint $table): void {
            $table->integer('blood_type_verified_by_admin_id')->nullable();
        });
        $this->addColumnIfMissing('donors', 'blood_type_verified_at', function (Blueprint $table): void {
            $table->dateTime('blood_type_verified_at')->nullable();
        });

        $this->addColumnIfMissing('donation_records', 'verified_blood_type_id', function (Blueprint $table): void {
            $table->integer('verified_blood_type_id')->nullable();
        });
        $this->addColumnIfMissing('donation_records', 'deferred_reason', function (Blueprint $table): void {
            $table->text('deferred_reason')->nullable();
        });
        $this->addColumnIfMissing('donation_records', 'recorded_by_admin_id', function (Blueprint $table): void {
            $table->integer('recorded_by_admin_id')->nullable();
        });
        $this->addColumnIfMissing('donation_records', 'created_at', function (Blueprint $table): void {
            $table->timestamp('created_at')->nullable();
        });
        $this->addColumnIfMissing('donation_records', 'updated_at', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable();
        });

        // AppointmentBookingService and DonationEventService still send the
        // push_sent attribute when creating notifications. Adding the field
        // keeps those workflows usable against the older dump.
        $this->addColumnIfMissing('notifications', 'push_sent', function (Blueprint $table): void {
            $table->boolean('push_sent')->default(false);
        });

        $this->ensureScreeningQuestionsTable();
    }

    private function addColumnIfMissing(string $tableName, string $column, callable $definition): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            Schema::table($tableName, $definition);
        }
    }

    private function ensureScreeningQuestionsTable(): void
    {
        if (! Schema::hasTable('screening_questions')) {
            Schema::create('screening_questions', function (Blueprint $table): void {
                $table->increments('question_id');
                $table->string('question_text', 500);
                $table->string('followup_prompt', 500)->nullable();
                $table->enum('followup_trigger', ['yes', 'no'])->nullable();
                $table->integer('question_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('extra_data')->nullable();
                $table->enum('risk_level', ['safe', 'auto_reject', 'for_review', 'temporary_defer'])->default('safe');
                $table->enum('trigger_answer', ['yes', 'no'])->nullable();
                $table->integer('deferral_days')->nullable();
                $table->text('recommendation_message')->nullable();
                $table->timestamps();
            });

            return;
        }

        $this->addColumnIfMissing('screening_questions', 'followup_prompt', function (Blueprint $table): void {
            $table->string('followup_prompt', 500)->nullable();
        });
        $this->addColumnIfMissing('screening_questions', 'followup_trigger', function (Blueprint $table): void {
            $table->enum('followup_trigger', ['yes', 'no'])->nullable();
        });
        $this->addColumnIfMissing('screening_questions', 'question_order', function (Blueprint $table): void {
            $table->integer('question_order')->default(0);
        });
        $this->addColumnIfMissing('screening_questions', 'is_active', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });
        $this->addColumnIfMissing('screening_questions', 'risk_level', function (Blueprint $table): void {
            $table->enum('risk_level', ['safe', 'auto_reject', 'for_review', 'temporary_defer'])->default('safe');
        });
        $this->addColumnIfMissing('screening_questions', 'trigger_answer', function (Blueprint $table): void {
            $table->enum('trigger_answer', ['yes', 'no'])->nullable();
        });
        $this->addColumnIfMissing('screening_questions', 'deferral_days', function (Blueprint $table): void {
            $table->integer('deferral_days')->nullable();
        });
        $this->addColumnIfMissing('screening_questions', 'recommendation_message', function (Blueprint $table): void {
            $table->text('recommendation_message')->nullable();
        });

        if (Schema::hasColumn('screening_questions', 'sort_order')) {
            DB::table('screening_questions')
                ->where(function ($query): void {
                    $query->whereNull('question_order')->orWhere('question_order', 0);
                })
                ->update(['question_order' => DB::raw('sort_order')]);
        }
    }

    private function seedBloodTypes(): void
    {
        foreach (self::BLOOD_TYPES as $bloodType) {
            $row = DB::table('blood_types')->where('blood_type', $bloodType)->first();
            if (! $row) {
                $id = (int) DB::table('blood_types')->insertGetId([
                    'blood_type' => $bloodType,
                ], 'blood_type_id');
            } else {
                $id = (int) $row->blood_type_id;
            }

            $this->bloodTypeIds[$bloodType] = $id;
        }
    }

    private function seedLocations(): void
    {
        $locations = [
            ['Adya', 'DEMO Seed Road 01', 13.92790, 121.15750],
            ['Anilao', 'DEMO Seed Road 02', 13.95340, 121.17610],
            ['Antipolo del Norte', 'DEMO Seed Road 03', 13.93920, 121.14330],
            ['Antipolo del Sur', 'DEMO Seed Road 04', 13.92560, 121.14590],
            ['Bagong Pook', 'DEMO Seed Road 05', 13.94880, 121.15570],
            ['Banay-banay', 'DEMO Seed Road 06', 13.96410, 121.17140],
            ['Bolbok', 'DEMO Seed Road 07', 13.91790, 121.16790],
            ['Bugtong', 'DEMO Seed Road 08', 13.94420, 121.18180],
            ['Bulacnin', 'DEMO Seed Road 09', 13.96900, 121.15160],
            ['Calamias', 'DEMO Seed Road 10', 13.91170, 121.18530],
            ['Calawit', 'DEMO Seed Road 11', 13.93690, 121.19340],
            ['Pinagkawitan', 'DEMO Seed Road 12', 13.95680, 121.12690],
            ['Sico', 'DEMO Seed Road 13', 13.91540, 121.13720],
            ['Tambo', 'DEMO Seed Road 14', 13.94710, 121.16980],
            ['Tibig', 'DEMO Seed Road 15', 13.97920, 121.18120],
        ];

        foreach ($locations as $index => [$barangay, $street, $latitude, $longitude]) {
            $payload = [
                'street_address' => $street,
                'barangay_name' => $barangay,
                'barangay_code' => 'DEMO-LIPA-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'city' => 'Lipa City',
                'province' => 'Batangas',
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];

            $this->locationIds[$index] = $this->upsertBy('locations', [
                'street_address' => $street,
            ], $payload, 'location_id');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function questionDefinitions(): array
    {
        return [
            [
                'text' => 'DEMO - Did you sleep at least 5 to 6 hours last night?',
                'followup_prompt' => 'How many hours did you sleep?',
                'followup_trigger' => 'no',
                'risk_level' => 'temporary_defer',
                'trigger_answer' => 'no',
                'deferral_days' => 1,
                'recommendation_message' => 'Please get enough rest before donating blood.',
            ],
            [
                'text' => 'DEMO - Did you eat a proper meal before donating blood?',
                'followup_prompt' => 'When was your last meal?',
                'followup_trigger' => 'no',
                'risk_level' => 'temporary_defer',
                'trigger_answer' => 'no',
                'deferral_days' => 1,
                'recommendation_message' => 'Please eat a proper meal before donating blood.',
            ],
            [
                'text' => 'DEMO - Have you consumed alcohol within the last 24 hours?',
                'followup_prompt' => 'When did you last consume alcohol?',
                'followup_trigger' => 'yes',
                'risk_level' => 'temporary_defer',
                'trigger_answer' => 'yes',
                'deferral_days' => 1,
                'recommendation_message' => 'Please avoid alcohol before donating blood.',
            ],
            [
                'text' => 'DEMO - Do you currently have fever, cough, colds, or any infection?',
                'followup_prompt' => 'Please describe your current symptoms.',
                'followup_trigger' => 'yes',
                'risk_level' => 'temporary_defer',
                'trigger_answer' => 'yes',
                'deferral_days' => 7,
                'recommendation_message' => 'Please recover first before donating blood.',
            ],
            [
                'text' => 'DEMO - Have you had a tattoo, piercing, or surgery recently?',
                'followup_prompt' => 'Please indicate when it happened.',
                'followup_trigger' => 'yes',
                'risk_level' => 'for_review',
                'trigger_answer' => 'yes',
                'deferral_days' => null,
                'recommendation_message' => 'Your answer requires review by authorized personnel.',
            ],
            [
                'text' => 'DEMO - Do you weigh at least the minimum required weight for blood donation?',
                'followup_prompt' => 'Please indicate your current weight.',
                'followup_trigger' => 'no',
                'risk_level' => 'for_review',
                'trigger_answer' => 'no',
                'deferral_days' => null,
                'recommendation_message' => 'Your weight must be reviewed before donation.',
            ],
            [
                'text' => 'DEMO - Do you currently have high or low blood pressure?',
                'followup_prompt' => 'Please indicate your latest blood pressure if known.',
                'followup_trigger' => 'yes',
                'risk_level' => 'for_review',
                'trigger_answer' => 'yes',
                'deferral_days' => null,
                'recommendation_message' => 'Your blood pressure must be checked before donation.',
            ],
            [
                'text' => 'DEMO - Have you ever been diagnosed with a blood-borne infection?',
                'followup_prompt' => 'Please provide any relevant information for authorized personnel.',
                'followup_trigger' => 'yes',
                'risk_level' => 'auto_reject',
                'trigger_answer' => 'yes',
                'deferral_days' => null,
                'recommendation_message' => 'Please consult authorized medical personnel before donating.',
            ],
            [
                'text' => 'DEMO - Are you currently pregnant or within the applicable postpartum deferral period?',
                'followup_prompt' => 'Please provide the relevant date if a medical review is needed.',
                'followup_trigger' => 'yes',
                'risk_level' => 'auto_reject',
                'trigger_answer' => 'yes',
                'deferral_days' => null,
                'recommendation_message' => 'Please follow medical guidance before attempting to donate.',
            ],
            [
                'text' => 'DEMO - Have you been advised by a healthcare professional not to donate blood?',
                'followup_prompt' => 'Please describe the advice for authorized personnel.',
                'followup_trigger' => 'yes',
                'risk_level' => 'auto_reject',
                'trigger_answer' => 'yes',
                'deferral_days' => null,
                'recommendation_message' => 'Please follow your healthcare professional\'s advice.',
            ],
        ];
    }

    private function seedQuestions(): void
    {
        $this->questionDefinitions = $this->questionDefinitions();

        foreach ($this->questionDefinitions as $order => $definition) {
            $screeningPayload = [
                'question_text' => $definition['text'],
                'followup_prompt' => $definition['followup_prompt'],
                'followup_trigger' => $definition['followup_trigger'],
                'question_order' => $order + 1,
                'is_active' => true,
                'risk_level' => $definition['risk_level'],
                'trigger_answer' => $definition['trigger_answer'],
                'deferral_days' => $definition['deferral_days'],
                'recommendation_message' => $definition['recommendation_message'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->screeningQuestionIds[$order] = $this->upsertBy(
                'screening_questions',
                ['question_text' => $definition['text']],
                $screeningPayload,
                'question_id'
            );

            $eligibilityPayload = [
                'question_text' => $definition['text'],
                'question_type' => 'yes_no',
                'is_disqualifying' => $definition['risk_level'] === 'auto_reject',
                'sort_order' => $order + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->eligibilityQuestionIds[$order] = $this->upsertBy(
                'eligibility_questions',
                ['question_text' => $definition['text']],
                $eligibilityPayload,
                'question_id'
            );
        }
    }

    private function seedDonors(): void
    {
        $firstNames = [
            'Althea', 'Andrei', 'Angela', 'Anton', 'Beatrice', 'Bianca', 'Carlo', 'Celine',
            'Clarisse', 'Danica', 'Daniel', 'Daphne', 'Daryl', 'Eloisa', 'Emmanuel', 'Erika',
            'Francis', 'Gabriela', 'Gian', 'Giselle', 'Hannah', 'Harold', 'Iñigo', 'Isabela',
            'Janelle', 'Jericho', 'Joanna', 'Joshua', 'Katrina', 'Kenji', 'Kristine', 'Lara',
            'Leandro', 'Lianne', 'Lorenzo', 'Ma. Cristina', 'Marvin', 'Micaela', 'Miguel', 'Nadine',
            'Nathaniel', 'Nicole', 'Paolo', 'Patricia', 'Rafael', 'Ramona', 'Renz', 'Roselle',
            'Samuel', 'Samantha', 'Santino', 'Sarah', 'Sebastian', 'Sheena', 'Tristan', 'Valerie',
            'Victor', 'Vina', 'Wilfred', 'Yasmin', 'Zachary', 'Aira', 'Benedict', 'Camille',
            'Dianne', 'Enzo', 'Faye', 'Gabe', 'Hazel', 'Ivy', 'Joaquin', 'Kyla', 'Luis', 'Mara',
            'Noel', 'Olivia', 'Peter', 'Queenie',
        ];
        $lastNames = [
            'Agoncillo', 'Bautista', 'Castillo', ' dela Cruz', 'De Leon', 'Del Rosario', 'Evangelista',
            'Fernandez', 'Garcia', 'Hernandez', 'Javier', 'Lacson', 'Manalo', 'Mendoza', 'Navarro',
            'Ocampo', 'Pascual', 'Reyes', 'Rivera', 'Santos', 'Soriano', 'Tan', 'Torres', 'Villanueva',
        ];

        for ($index = 0; $index < 80; $index++) {
            $number = $index + 1;
            $email = 'demo.donor' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '@example.test';
            $contact = '+63917' . str_pad((string) (1000000 + $number), 7, '0', STR_PAD_LEFT);
            $verificationStatus = match (true) {
                $number <= 40 => 'verified',
                $number <= 55 => 'pending',
                $number <= 65 => 'unverified',
                default => 'rejected',
            };
            $bloodTypeStatus = $number <= 35 ? 'verified' : ($number <= 55 ? 'self_reported' : 'not_yet_determined');
            $birthdate = $this->today->copy()->subYears(20 + ($index % 31))->subDays($index * 11)->toDateString();
            $dateRegistered = $this->today->copy()->subDays(10 + (($index * 13) % 180))->setTime(9 + ($index % 8), ($index * 7) % 60)->toDateTimeString();

            $payload = [
                'first_name' => $firstNames[$index % count($firstNames)],
                'last_name' => trim($lastNames[$index % count($lastNames)]),
                'gender' => $index % 3 === 0 ? 'Female' : ($index % 3 === 1 ? 'Male' : 'Other'),
                'birthdate' => $birthdate,
                'contact_number' => $contact,
                'blood_type_id' => $this->bloodTypeIds[self::BLOOD_TYPES[$index % count(self::BLOOD_TYPES)]],
                'blood_type_status' => $bloodTypeStatus,
                'blood_type_verified_by_admin_id' => null,
                'blood_type_verified_at' => $bloodTypeStatus === 'verified'
                    ? $this->today->copy()->subDays(20 + $index)->setTime(10, 0)->toDateTimeString()
                    : null,
                'location_id' => $this->locationIds[$index % count($this->locationIds)],
                'date_registered' => $dateRegistered,
                'verification_status' => $verificationStatus,
            ];

            $existingAuth = DB::table('donor_authentication')->where('email', $email)->first();
            $donorId = $existingAuth?->donor_id;
            if (! $donorId) {
                $existingDonor = DB::table('donors')->where('contact_number', $contact)->first();
                $donorId = $existingDonor?->donor_id;
            }

            if ($donorId) {
                DB::table('donors')->where('donor_id', $donorId)->update($this->filterPayload('donors', $payload));
                $donorId = (int) $donorId;
            } else {
                $donorId = (int) DB::table('donors')->insertGetId(
                    $this->filterPayload('donors', $payload),
                    'donor_id'
                );
            }

            $this->donorIds[$index] = $donorId;
            $this->donorEmails[$index] = $email;

            $isVerifiedAccount = $number <= 40;
            $authPayload = [
                'donor_id' => $donorId,
                'email' => $email,
                'password' => $this->passwordHash(),
                'is_verified' => $isVerifiedAccount,
                'verification_token' => null,
                'verification_sent_at' => $isVerifiedAccount ? null : $this->today->copy()->subDays($index % 5)->setTime(8, 0)->toDateTimeString(),
                'verified_at' => $isVerifiedAccount ? $this->today->copy()->subDays(5 + ($index % 20))->setTime(8, 0)->toDateTimeString() : null,
                'created_at' => $dateRegistered,
            ];

            $this->upsertBy('donor_authentication', ['email' => $email], $authPayload, 'auth_id');
        }
    }

    private function passwordHash(): string
    {
        return $this->passwordHash ??= Hash::make(self::PASSWORD);
    }

    private function seedFacilities(): void
    {
        $facilities = [
            ['DEMO Lipa Central Medical Center', 'hospital', 'Adya', 13.92980, 121.15930, 'active'],
            ['DEMO City Blood Bank', 'blood_bank', 'Banay-banay', 13.96320, 121.17010, 'active'],
            ['DEMO Community Health Center', 'health_center', 'Bolbok', 13.91870, 121.16890, 'active'],
            ['DEMO South Lipa Clinic', 'clinic', 'Sico', 13.91620, 121.13810, 'active'],
            ['DEMO North Lipa Hospital Annex', 'hospital', 'Tibig', 13.97810, 121.17970, 'active'],
            ['DEMO Barangay Wellness Clinic', 'clinic', 'Bulacnin', 13.96810, 121.15020, 'active'],
            ['DEMO Mobile Blood Collection Hub', 'blood_bank', 'Tambo', 13.94640, 121.16870, 'active'],
            ['DEMO Riverside Health Center', 'health_center', 'Calamias', null, null, 'active'],
            ['DEMO Training Clinic', 'clinic', 'Pinagkawitan', 13.95730, 121.12580, 'inactive'],
            ['DEMO Reserve Blood Storage', 'blood_bank', 'Calawit', null, null, 'inactive'],
        ];

        foreach ($facilities as $index => [$name, $type, $barangay, $latitude, $longitude, $status]) {
            $payload = [
                'facility_name' => $name,
                'facility_type' => $type,
                'address' => 'DEMO facility address, ' . $barangay . ', Lipa City',
                'barangay_name' => $barangay,
                'city' => 'Lipa City',
                'province' => 'Batangas',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'contact_number' => '+63900' . str_pad((string) (5000000 + $index), 7, '0', STR_PAD_LEFT),
                'status' => $status,
                'created_at' => $this->today->copy()->subDays(60 - $index)->setTime(9, 0)->toDateTimeString(),
                'updated_at' => $this->today->copy()->subDays($index % 10)->setTime(9, 0)->toDateTimeString(),
            ];

            $this->facilityIds[$index] = $this->upsertBy(
                'facilities',
                ['facility_name' => $name],
                $payload,
                'facility_id'
            );
        }
    }

    private function seedEvents(): void
    {
        $events = [
            ['DEMO Event 01 - Summer Blood Drive', -90, 'completed', 20, 0],
            ['DEMO Event 02 - Community Donor Day', -70, 'closed', 20, 1],
            ['DEMO Event 03 - Barangay Outreach', -45, 'cancelled', 20, 2],
            ['DEMO Event 04 - Health Week Collection', -20, 'completed', 18, 3],
            ['DEMO Event 05 - South Lipa Drive', -10, 'closed', 16, 4],
            ['DEMO Event 06 - Today Check-in Simulation', 0, 'open', 16, 5],
            ['DEMO Event 07 - Open Community Drive', 7, 'open', 40, 6],
            ['DEMO Event 08 - Nearly Full Donor Day', 14, 'open', 25, 7],
            ['DEMO Event 09 - Full Capacity Test Event', 21, 'open', 10, 8],
            ['DEMO Event 10 - Nearly Full Health Camp', 28, 'open', 12, 9],
            ['DEMO Event 11 - Closed Future Schedule', 35, 'closed', 20, 10],
            ['DEMO Event 12 - Cancelled Future Drive', 42, 'cancelled', 20, 11],
            ['DEMO Event 13 - Reschedule Workflow Event', 49, 'open', 30, 12],
            ['DEMO Event 14 - Large Availability Event', 70, 'open', 30, 13],
        ];

        foreach ($events as $index => [$title, $dayOffset, $status, $capacity, $locationIndex]) {
            $date = $this->today->copy()->addDays($dayOffset);
            $facilityId = $this->facilityIds[$locationIndex] ?? null;
            $facilityName = $facilityId
                ? (string) (DB::table('facilities')->where('facility_id', $facilityId)->value('facility_name') ?? 'DEMO Lipa Collection Site')
                : 'DEMO Lipa Collection Site';
            $payload = [
                'title' => $title,
                'event_date' => $date->toDateString(),
                'start_time' => '07:30:00',
                'end_time' => '17:00:00',
                'location_name' => $facilityName,
                'address' => 'DEMO ' . ($this->barangayForFacility($locationIndex) ?? 'Lipa City') . ', Lipa City, Batangas',
                'max_capacity' => $capacity,
                'status' => $status,
                'created_by_admin_id' => null,
                'created_at' => $this->today->copy()->subDays(45 - min(30, $index))->setTime(8, 0)->toDateTimeString(),
                'updated_at' => $this->today->copy()->subDays($index % 6)->setTime(8, 0)->toDateTimeString(),
            ];

            $this->eventIds[$index] = $this->upsertBy('donation_events', ['title' => $title], $payload, 'event_id');
        }
    }

    private function barangayForFacility(int $facilityIndex): ?string
    {
        $rows = [
            'Adya', 'Banay-banay', 'Bolbok', 'Sico', 'Tibig', 'Bulacnin', 'Tambo', 'Calamias', 'Pinagkawitan', 'Calawit',
        ];

        return $rows[$facilityIndex] ?? null;
    }

    private function seedAppointmentsAndDonations(): void
    {
        $statusSets = [
            array_fill(0, 12, 'completed'),
            array_merge(array_fill(0, 8, 'completed'), array_fill(0, 2, 'no_show')),
            array_fill(0, 5, 'cancelled'),
            array_merge(array_fill(0, 8, 'completed'), array_fill(0, 2, 'deferred_on_site')),
            array_merge(array_fill(0, 6, 'completed'), array_fill(0, 3, 'no_show')),
            array_merge(array_fill(0, 4, 'checked_in'), array_fill(0, 3, 'confirmed')),
            array_fill(0, 20, 'confirmed'),
            array_fill(0, 15, 'confirmed'),
            array_fill(0, 10, 'confirmed'),
            array_merge(array_fill(0, 8, 'confirmed'), array_fill(0, 2, 'rescheduled')),
            array_fill(0, 4, 'cancelled'),
            array_fill(0, 6, 'cancelled'),
            array_merge(array_fill(0, 6, 'rescheduled'), array_fill(0, 4, 'confirmed')),
            array_fill(0, 4, 'confirmed'),
        ];

        foreach ($statusSets as $eventIndex => $statuses) {
            $event = DB::table('donation_events')->where('event_id', $this->eventIds[$eventIndex])->first();
            if (! $event) {
                continue;
            }

            $usedDonors = [];
            foreach ($statuses as $slot => $status) {
                $donorId = $this->appointmentDonor($eventIndex, $slot, $usedDonors);
                $usedDonors[] = $donorId;
                $appointmentDate = (string) $event->event_date;
                $appointmentTime = sprintf('%02d:%02d:00', 8 + ($slot % 8), ($slot * 7) % 60);
                $createdAt = Carbon::parse($appointmentDate)->subDays($eventIndex % 4 + 1)->setTime(7, 0)->toDateTimeString();

                $payload = [
                    'donor_id' => $donorId,
                    'event_id' => $this->eventIds[$eventIndex],
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                    'status' => $status,
                    'completed_at' => $status === 'completed'
                        ? Carbon::parse($appointmentDate . ' ' . $appointmentTime)->addHours(2)->toDateTimeString()
                        : null,
                    'checked_in_at' => in_array($status, ['checked_in', 'completed', 'deferred_on_site'], true)
                        ? Carbon::parse($appointmentDate . ' ' . $appointmentTime)->subMinutes(20)->toDateTimeString()
                        : null,
                    'cancellation_reason' => $status === 'cancelled' ? 'DEMO cancellation: schedule changed.' : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'admin_id' => null,
                    'donation_center' => 'DEMO seed: ' . (string) $event->location_name,
                ];

                $appointmentId = $this->upsertBy(
                    'appointments',
                    [
                        'donor_id' => $donorId,
                        'event_id' => $this->eventIds[$eventIndex],
                        'appointment_date' => $appointmentDate,
                        'appointment_time' => $appointmentTime,
                    ],
                    $payload,
                    'appointment_id'
                );

                if ($status === 'completed') {
                    $this->seedDonationRecord($appointmentId, $donorId, $appointmentDate, 'completed', $eventIndex, $slot);
                } elseif ($status === 'deferred_on_site') {
                    $this->seedDonationRecord($appointmentId, $donorId, $appointmentDate, 'deferred', $eventIndex, $slot);
                }
            }
        }
    }

    /** @param array<int, int> $usedDonors */
    private function appointmentDonor(int $eventIndex, int $slot, array $usedDonors): int
    {
        $pool = match ($eventIndex) {
            0, 1 => array_slice($this->donorIds, 0, 30),
            2, 10, 11 => array_slice($this->donorIds, 55, 25),
            3 => array_slice($this->donorIds, 40, 10),
            4 => array_slice($this->donorIds, 50, 10),
            5 => array_slice($this->donorIds, 10, 30),
            default => array_slice($this->donorIds, 15, 40),
        };

        if ($pool === []) {
            throw new RuntimeException('Demo donor pool is empty while creating appointments.');
        }

        for ($offset = 0; $offset < count($pool); $offset++) {
            $candidate = $pool[($slot + $offset) % count($pool)];
            if (! in_array($candidate, $usedDonors, true)) {
                return (int) $candidate;
            }
        }

        return (int) $pool[$slot % count($pool)];
    }

    private function seedDonationRecord(int $appointmentId, int $donorId, string $date, string $status, int $eventIndex, int $slot): void
    {
        $bloodTypeId = DB::table('donors')->where('donor_id', $donorId)->value('blood_type_id');
        $isDeferred = $status === 'deferred';
        $payload = [
            'donor_id' => $donorId,
            'appointment_id' => $appointmentId,
            'donation_status' => $status,
            'donation_date' => $date,
            'blood_units' => $isDeferred ? 0 : (1 + (($eventIndex + $slot) % 2)),
            'verified_blood_type_id' => $isDeferred ? null : $bloodTypeId,
            'remarks' => $isDeferred ? 'DEMO on-site deferral record.' : 'DEMO completed donation record.',
            'deferred_reason' => $isDeferred ? 'DEMO temporary deferral during on-site screening.' : null,
            'recorded_by_admin_id' => null,
            'created_at' => Carbon::parse($date)->setTime(15, 0)->toDateTimeString(),
            'updated_at' => Carbon::parse($date)->setTime(15, 0)->toDateTimeString(),
        ];

        $this->upsertBy('donation_records', ['appointment_id' => $appointmentId], $payload, 'donation_id');
    }

    private function seedEligibility(): void
    {
        foreach ($this->donorIds as $index => $donorId) {
            $number = $index + 1;
            $status = match (true) {
                $number <= 40 => 'eligible',
                $number <= 55 => 'temporary_deferred',
                $number <= 65 => 'not_eligible',
                $number <= 75 => 'for_review',
                default => 'pending',
            };
            $answers = $this->answersForStatus($number, $status);
            $submittedAt = $this->today->copy()->subDays(($index * 5) % 170)->setTime(11, ($index * 3) % 60);

            $submissionId = $this->upsertBy('eligibility_submissions', [
                'donor_id' => $donorId,
                'source' => 'demo_seed_v1',
            ], [
                'donor_id' => $donorId,
                'submitted_at' => $submittedAt->toDateTimeString(),
                'source' => 'demo_seed_v1',
                'created_at' => $submittedAt->toDateTimeString(),
                'updated_at' => $submittedAt->toDateTimeString(),
            ], 'submission_id');

            foreach ($answers as $questionIndex => $answer) {
                $this->upsertBy('eligibility_answers', [
                    'submission_id' => $submissionId,
                    'question_id' => $this->eligibilityQuestionIds[$questionIndex],
                ], [
                    'submission_id' => $submissionId,
                    'question_id' => $this->eligibilityQuestionIds[$questionIndex],
                    'answer_value' => $answer,
                    'created_at' => $submittedAt->toDateTimeString(),
                    'updated_at' => $submittedAt->toDateTimeString(),
                ], 'answer_id');
            }

            $latestDonationDate = DB::table('donation_records')
                ->where('donor_id', $donorId)
                ->where('donation_status', 'completed')
                ->max('donation_date');
            $nextEligibleDate = $this->nextEligibleDate($number, $status, $latestDonationDate);
            $source = $status === 'for_review' ? 'admin_review' : 'auto';
            $reason = $this->eligibilityReason($status, $number, $latestDonationDate);
            $recommendation = $this->eligibilityRecommendation($status);

            $eligibilityId = $this->upsertDemoEligibility($donorId, [
                'last_donation_date' => $latestDonationDate,
                'next_eligible_date' => $nextEligibleDate,
                'status' => $status,
                'result_reason' => $reason,
                'recommendation_message' => $recommendation,
                'source' => $source,
                'reviewed_by_admin_id' => null,
                'reviewed_at' => null,
                'review_notes' => 'DEMO seed eligibility scenario for donor ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ]);

            foreach ($answers as $questionIndex => $answer) {
                $this->upsertBy('donor_screening_answers', [
                    'eligibility_id' => $eligibilityId,
                    'question_id' => $this->screeningQuestionIds[$questionIndex],
                ], [
                    'eligibility_id' => $eligibilityId,
                    'question_id' => $this->screeningQuestionIds[$questionIndex],
                    'answer' => $answer,
                    'followup_answer' => $this->followupAnswer($questionIndex, $answer),
                ], 'answer_id');
            }
        }
    }

    /** @return array<int, string> */
    private function answersForStatus(int $number, string $status): array
    {
        $answers = [];
        foreach ($this->questionDefinitions as $index => $definition) {
            $risk = $definition['risk_level'];
            $trigger = $definition['trigger_answer'];
            $answers[$index] = $risk === 'safe' ? 'yes' : ($trigger === 'yes' ? 'no' : 'yes');
        }

        if ($status === 'temporary_deferred') {
            $questionIndex = $number <= 45 ? 3 : ($number <= 50 ? 0 : 1);
            $answers[$questionIndex] = $this->questionDefinitions[$questionIndex]['trigger_answer'];
        } elseif ($status === 'not_eligible') {
            $answers[7] = 'yes';
        } elseif ($status === 'for_review') {
            $answers[$number <= 70 ? 4 : 5] = $number <= 70 ? 'yes' : 'no';
        }

        return $answers;
    }

    private function followupAnswer(int $questionIndex, string $answer): ?string
    {
        $trigger = $this->questionDefinitions[$questionIndex]['trigger_answer'];

        return $answer === $trigger
            ? 'DEMO follow-up response for question ' . ($questionIndex + 1)
            : 'DEMO - no follow-up required.';
    }

    private function nextEligibleDate(int $number, string $status, ?string $latestDonationDate): ?string
    {
        if ($status === 'eligible') {
            if ($latestDonationDate) {
                return Carbon::parse($latestDonationDate)->addDays(56)->lte($this->today)
                    ? Carbon::parse($latestDonationDate)->addDays(56)->toDateString()
                    : null;
            }

            return $number % 3 === 0 ? $this->today->copy()->subDays(2)->toDateString() : null;
        }

        if ($status === 'temporary_deferred') {
            if ($latestDonationDate) {
                $candidate = Carbon::parse($latestDonationDate)->addDays(56);
                if ($candidate->gt($this->today)) {
                    return $candidate->toDateString();
                }
            }

            return $this->today->copy()->addDays($number % 2 === 0 ? 7 : 2)->toDateString();
        }

        return null;
    }

    private function eligibilityReason(string $status, int $number, ?string $latestDonationDate): string
    {
        return match ($status) {
            'eligible' => $latestDonationDate
                ? 'DEMO seed: 56-day waiting period passed after the last completed donation.'
                : 'DEMO seed: passed initial eligibility screening; no waiting period is active.',
            'temporary_deferred' => $latestDonationDate
                ? 'DEMO seed: recent completed donation is still within the 56-day waiting period.'
                : 'DEMO seed: temporary deferral scenario from the screening answers.',
            'not_eligible' => 'DEMO seed: an automatic not-eligible screening answer was matched.',
            'for_review' => 'DEMO seed: a screening answer requires authorized personnel review.',
            default => 'DEMO seed: eligibility screening is awaiting a completed review flow.',
        };
    }

    private function eligibilityRecommendation(string $status): string
    {
        return match ($status) {
            'eligible' => 'DEMO: You may proceed to appointment planning.',
            'temporary_deferred' => 'DEMO: Please wait until the displayed next eligible date.',
            'not_eligible' => 'DEMO: Please follow medical guidance before attempting another donation.',
            'for_review' => 'DEMO: An authorized reviewer should assess this submission.',
            default => 'DEMO: Complete the next review step before booking.',
        };
    }

    /** @param array<string, mixed> $payload */
    private function upsertDemoEligibility(int $donorId, array $payload): int
    {
        $query = DB::table('eligibility_status')
            ->where('donor_id', $donorId)
            ->where('review_notes', 'like', 'DEMO seed%');
        $existing = $query->orderByDesc('eligibility_id')->first();

        if ($existing) {
            DB::table('eligibility_status')
                ->where('eligibility_id', $existing->eligibility_id)
                ->update($this->filterPayload('eligibility_status', $payload));

            return (int) $existing->eligibility_id;
        }

        return (int) DB::table('eligibility_status')->insertGetId(
            $this->filterPayload('eligibility_status', ['donor_id' => $donorId] + $payload),
            'eligibility_id'
        );
    }

    private function seedVerifications(): void
    {
        foreach ($this->donorIds as $index => $donorId) {
            $number = $index + 1;
            if ($number > 65) {
                continue;
            }

            $status = $number <= 40 ? 'verified' : ($number <= 55 ? 'pending' : 'rejected');
            $path = 'demo/verifications/demo-id-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '.jpg';
            $payload = [
                'donor_id' => $donorId,
                'document_type' => $number % 3 === 0 ? 'school_id' : ($number % 3 === 1 ? 'national_id' : 'barangay_certificate'),
                'document_path' => $path,
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? 'DEMO rejection: placeholder document needs review.' : null,
                'reviewed_by_admin_id' => null,
                'reviewed_at' => $status === 'pending' ? null : $this->today->copy()->subDays($number % 12)->setTime(14, 0)->toDateTimeString(),
                'created_at' => $this->today->copy()->subDays(30 + ($number % 20))->setTime(10, 0)->toDateTimeString(),
                'updated_at' => $this->today->copy()->subDays($number % 12)->setTime(14, 0)->toDateTimeString(),
            ];

            $this->upsertBy('donor_verifications', ['document_path' => $path], $payload, 'verification_id');
        }
    }

    private function seedFacilityInventory(): void
    {
        foreach ($this->facilityIds as $facilityIndex => $facilityId) {
            foreach (self::BLOOD_TYPES as $typeIndex => $bloodType) {
                $units = match ($facilityIndex % 4) {
                    0 => ($typeIndex % 3 === 0 ? 0 : 1 + (($typeIndex + $facilityIndex) % 5)),
                    1 => 1 + (($typeIndex * 2 + $facilityIndex) % 5),
                    2 => 7 + (($typeIndex * 3 + $facilityIndex) % 9),
                    default => 16 + (($typeIndex + $facilityIndex) % 10),
                };
                $threshold = [3, 5, 6][$facilityIndex % 3];
                $reserved = min($units, intdiv($units, 4));
                $lastUpdated = $this->today->copy()->subDays(($facilityIndex + $typeIndex) % 12)->setTime(16, ($typeIndex * 5) % 60);

                $inventoryPayload = [
                    'facility_id' => $facilityId,
                    'blood_type_id' => $this->bloodTypeIds[$bloodType],
                    'available_units' => $units,
                    'reserved_units' => $reserved,
                    'low_stock_threshold' => $threshold,
                    'last_updated' => $lastUpdated->toDateTimeString(),
                    'updated_by_admin_id' => null,
                ];
                $this->upsertBy('facility_blood_inventory', [
                    'facility_id' => $facilityId,
                    'blood_type_id' => $this->bloodTypeIds[$bloodType],
                ], $inventoryPayload, 'inventory_id');

                $initial = max(0, $units - 3);
                $intermediate = $units + 1;
                $logRows = [
                    [$initial, 'set', 'DEMO initial inventory'],
                    [$intermediate, 'add', 'DEMO stock adjustment'],
                    [$units, $intermediate > $units ? 'deduct' : 'correction', 'DEMO stock correction'],
                ];
                $previous = 0;
                foreach ($logRows as $logIndex => [$newUnits, $action, $reason]) {
                    $logReason = $reason . ' F' . str_pad((string) ($facilityIndex + 1), 2, '0', STR_PAD_LEFT)
                        . ' ' . $bloodType . ' #' . ($logIndex + 1);
                    $this->upsertBy('facility_blood_inventory_logs', [
                        'facility_id' => $facilityId,
                        'blood_type_id' => $this->bloodTypeIds[$bloodType],
                        'reason' => $logReason,
                    ], [
                        'facility_id' => $facilityId,
                        'blood_type_id' => $this->bloodTypeIds[$bloodType],
                        'previous_units' => $previous,
                        'new_units' => $newUnits,
                        'change_amount' => $newUnits - $previous,
                        'action_type' => $action,
                        'reason' => $logReason,
                        'updated_by_admin_id' => null,
                        'created_at' => $lastUpdated->copy()->subDays(4 - $logIndex)->toDateTimeString(),
                    ],
                    'inventory_log_id'
                );
                $previous = $newUnits;
            }
        }
    }
    }

    private function seedBloodRequests(): void
    {
        $statuses = ['draft', 'open', 'in_progress', 'fulfilled', 'cancelled', 'expired'];
        $urgencies = ['normal', 'urgent', 'emergency'];

        for ($index = 0; $index < 25; $index++) {
            $number = $index + 1;
            $status = $statuses[$index % count($statuses)];
            $urgency = $index % 7 === 0 ? 'emergency' : ($index % 3 === 0 ? 'urgent' : 'normal');
            $requestType = $index % 2 === 0 ? 'blood_request' : 'replacement_donor';
            $allowOther = $index % 3 === 0;
            $required = 1 + ($index % 5);
            $specific = $allowOther ? 1 : $required;
            $reference = 'DEMO-BR-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $createdAt = $this->today->copy()->subDays(3 + (($index * 11) % 160))->setTime(10 + ($index % 6), 15);

            $payload = [
                'facility_id' => $this->facilityIds[$index % count($this->facilityIds)],
                'request_reference' => $reference,
                'patient_reference_code' => 'DEMO-PAT-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'request_type' => $requestType,
                'needed_blood_type_id' => $this->bloodTypeIds[self::BLOOD_TYPES[$index % count(self::BLOOD_TYPES)]],
                'required_donors' => $required,
                'total_donors_needed' => $required,
                'specific_match_required' => $specific,
                'specific_blood_type_required_count' => $specific,
                'allow_other_blood_types' => $allowOther,
                'allow_any_blood_type_replacement' => $allowOther,
                'urgency' => $urgency,
                'status' => $status,
                'notes' => 'DEMO request for filter, matching, and status testing.',
                'created_by_admin_id' => null,
                'expires_at' => $status === 'expired'
                    ? $createdAt->copy()->addDays(2)->toDateTimeString()
                    : $this->today->copy()->addDays(7 + $index)->setTime(17, 0)->toDateTimeString(),
                'fulfilled_at' => $status === 'fulfilled'
                    ? $createdAt->copy()->addDays(4)->setTime(15, 0)->toDateTimeString()
                    : null,
                'cancelled_at' => $status === 'cancelled'
                    ? $createdAt->copy()->addDays(2)->setTime(15, 0)->toDateTimeString()
                    : null,
                'fulfillment_note' => $status === 'fulfilled' ? 'DEMO fulfillment scenario completed.' : null,
                'cancellation_reason' => $status === 'cancelled' ? 'DEMO cancellation scenario.' : null,
                'created_at' => $createdAt->toDateTimeString(),
                'updated_at' => $createdAt->copy()->addDays(1)->toDateTimeString(),
            ];

            $requestId = $this->upsertBy('blood_requests', ['request_reference' => $reference], $payload, 'request_id');
            $this->requestIds[$index] = $requestId;
            $this->seedRequestDonors($index, $requestId, $required, $specific, $allowOther, $status);
        }
    }

    private function seedRequestDonors(int $requestIndex, int $requestId, int $required, int $specific, bool $allowOther, string $requestStatus): void
    {
        $request = DB::table('blood_requests')->where('request_id', $requestId)->first();
        if (! $request) {
            return;
        }

        // Rebuild only the demo donor links for this demo request. This
        // repairs old versions of the dataset without touching any real
        // donor relationship someone may have added later.
        DB::table('blood_request_donors')
            ->where('request_id', $requestId)
            ->whereIn('donor_id', $this->donorIds)
            ->delete();

        $neededBloodTypeId = (int) $request->needed_blood_type_id;
        $exactPool = $this->eligibleMatchingDonors($neededBloodTypeId, true);
        $otherPool = $this->eligibleMatchingDonors($neededBloodTypeId, false);
        $used = [];
        $rows = [];

        if ($requestStatus === 'fulfilled') {
            for ($i = 0; $i < $specific; $i++) {
                $donor = $this->nextAvailableDonor($exactPool, $used);
                if ($donor === null) {
                    break;
                }
                $used[] = $donor;
                $rows[] = [$donor, 'exact', 'completed'];
            }
            for ($i = count($rows); $i < $required; $i++) {
                $pool = $allowOther ? $otherPool : $exactPool;
                $donor = $this->nextAvailableDonor($pool, $used);
                if ($donor === null) {
                    break;
                }
                $used[] = $donor;
                $rows[] = [$donor, $allowOther ? 'replacement_any' : 'exact', 'completed'];
            }
        } else {
            $candidateCount = min(6, max(2, $required + 1));
            for ($i = 0; $i < $candidateCount; $i++) {
                $isExact = ! $allowOther || $i % 2 === 0;
                $pool = $isExact ? $exactPool : $otherPool;
                $donor = $this->nextAvailableDonor($pool, $used);
                if ($donor === null) {
                    if (! $allowOther) {
                        break;
                    }

                    $donor = $this->nextAvailableDonor($isExact ? $otherPool : $exactPool, $used);
                    $isExact = ! $isExact;
                }
                if ($donor === null) {
                    break;
                }
                $used[] = $donor;
                $status = match ($i % 5) {
                    0 => 'candidate',
                    1 => 'notified',
                    2 => 'interested',
                    3 => 'declined',
                    default => 'confirmed',
                };
                $rows[] = [$donor, $isExact ? 'exact' : 'replacement_any', $status];
            }
        }

        foreach ($rows as $rowIndex => [$donorId, $matchType, $status]) {
            $notifiedAt = in_array($status, ['notified', 'interested', 'confirmed', 'completed'], true)
                ? $this->today->copy()->subDays(($requestIndex + $rowIndex) % 12)->setTime(13, 0)->toDateTimeString()
                : null;
            $respondedAt = in_array($status, ['interested', 'declined', 'confirmed', 'completed'], true)
                ? $this->today->copy()->subDays(($requestIndex + $rowIndex) % 8)->setTime(14, 0)->toDateTimeString()
                : null;

            $this->upsertBy('blood_request_donors', [
                'request_id' => $requestId,
                'donor_id' => $donorId,
            ], [
                'request_id' => $requestId,
                'donor_id' => $donorId,
                'match_type' => $matchType,
                'status' => $status,
                'notified_at' => $notifiedAt,
                'responded_at' => $respondedAt,
                'created_at' => $this->today->copy()->subDays(14 + $rowIndex)->setTime(12, 0)->toDateTimeString(),
                'updated_at' => $this->today->copy()->subDays($rowIndex % 5)->setTime(14, 0)->toDateTimeString(),
            ], 'id');
        }
    }

    /** @return array<int, int> */
    private function eligibleMatchingDonors(int $bloodTypeId, bool $exact): array
    {
        $rows = DB::table('donors as d')
            ->join('eligibility_status as es', function ($join): void {
                $join->on('es.donor_id', '=', 'd.donor_id')
                    ->where('es.review_notes', 'like', 'DEMO seed%');
            })
            ->whereIn('d.donor_id', $this->donorIds)
            ->where('d.verification_status', 'verified')
            ->where('d.blood_type_status', 'verified')
            ->where('es.status', 'eligible')
            ->where(function ($query): void {
                $query->whereNull('es.next_eligible_date')->orWhereDate('es.next_eligible_date', '<=', $this->today->toDateString());
            })
            ->whereNotIn('d.donor_id', $this->donorsWithUpcomingAppointments())
            ->when($exact, fn ($query) => $query->where('d.blood_type_id', $bloodTypeId))
            ->when(! $exact, fn ($query) => $query->where('d.blood_type_id', '<>', $bloodTypeId))
            ->orderBy('d.donor_id')
            ->pluck('d.donor_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $rows;
    }

    /** @return array<int, int> */
    private function donorsWithUpcomingAppointments(): array
    {
        return DB::table('appointments')
            ->whereIn('donor_id', $this->donorIds)
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereDate('appointment_date', '>=', $this->today->toDateString())
            ->pluck('donor_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();
    }

    /** @param array<int, int> $pool @param array<int, int> $used */
    private function nextAvailableDonor(array $pool, array $used): ?int
    {
        foreach ($pool as $donorId) {
            if (! in_array($donorId, $used, true)) {
                return (int) $donorId;
            }
        }

        return null;
    }

    private function seedNotifications(): void
    {
        $types = [
            'eligibility_result',
            'eligibility_deferred',
            'eligibility_reviewed',
            'donor_verification_approved',
            'donor_verification_rejected',
            'appointment_booked',
            'appointment_cancelled',
            'appointment_no_show',
            'donation_completed',
            'donation_deferred',
            'next_eligible_reminder',
            'blood_request_invitation',
            'blood_request_cancelled',
        ];

        foreach ($this->donorIds as $index => $donorId) {
            for ($notificationIndex = 0; $notificationIndex < 2; $notificationIndex++) {
                $type = $types[($index * 2 + $notificationIndex) % count($types)];
                $number = $index + 1;
                $message = 'DEMO notification ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '-' . ($notificationIndex + 1)
                    . ': ' . $this->notificationMessage($type);
                $createdAt = $this->today->copy()->subDays(($index * 3 + $notificationIndex) % 180)->setTime(9 + $notificationIndex, 30);

                $payload = [
                    'donor_id' => $donorId,
                    'message' => $message,
                    'notification_type' => $type,
                    'is_read' => ($index + $notificationIndex) % 3 === 0 ? 1 : 0,
                    'created_at' => $createdAt->toDateTimeString(),
                    'push_sent' => 0,
                ];

                $this->upsertBy('notifications', [
                    'donor_id' => $donorId,
                    'message' => $message,
                ], $payload, 'notification_id');
            }
        }
    }

    private function notificationMessage(string $type): string
    {
        return match ($type) {
            'eligibility_result' => 'Your eligibility result is available for review.',
            'eligibility_deferred' => 'Your donation eligibility is temporarily deferred.',
            'eligibility_reviewed' => 'Your eligibility review has been updated.',
            'donor_verification_approved' => 'Your identity verification was approved.',
            'donor_verification_rejected' => 'Your identity verification needs attention.',
            'appointment_booked' => 'Your donation appointment has been confirmed.',
            'appointment_cancelled' => 'A demo appointment was cancelled.',
            'appointment_no_show' => 'A demo appointment was recorded as no-show.',
            'donation_completed' => 'Thank you for completing a demo donation.',
            'donation_deferred' => 'Your on-site donation was deferred for follow-up.',
            'next_eligible_reminder' => 'You may be eligible again after the waiting period.',
            'blood_request_invitation' => 'You have a demo blood request invitation.',
            default => 'A demo blood request invitation was cancelled.',
        };
    }

    private function writeDemoPlaceholderFiles(): void
    {
        if (! Storage::disk('local')->exists('demo/verifications')) {
            Storage::disk('local')->makeDirectory('demo/verifications');
        }

        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z', true);

        for ($number = 1; $number <= 65; $number++) {
            $path = 'demo/verifications/demo-id-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '.jpg';
            if (! Storage::disk('local')->exists($path)) {
                Storage::disk('local')->put($path, $jpeg ?: 'DEMO placeholder document');
            }
        }
    }

    /** @return array<string, mixed> */
    private function protectedSnapshot(): array
    {
        $snapshot = [];
        foreach (self::PROTECTED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $snapshot[$table] = ['missing' => true];
                continue;
            }

            $rows = DB::table($table)->get()
                ->map(fn ($row): string => json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '')
                ->sort()
                ->values()
                ->all();
            $snapshot[$table] = [
                'count' => count($rows),
                'hash' => hash('sha256', implode("\n", $rows)),
            ];
        }

        return $snapshot;
    }

    /** @param array<string, mixed> $before */
    private function assertProtectedSnapshot(array $before): void
    {
        $after = $this->protectedSnapshot();
        if ($before !== $after) {
            throw new RuntimeException('Protected admin/security/system tables changed while seeding. Transaction aborted.');
        }
    }

    /** @return array<string, mixed> */
    private function filterPayload(string $table, array $payload): array
    {
        $columns = Schema::getColumnListing($table);

        return array_filter(
            $payload,
            static fn ($value, string $column): bool => in_array($column, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** @param array<string, mixed> $keys @param array<string, mixed> $payload */
    private function upsertBy(string $table, array $keys, array $payload, string $primaryKey): int
    {
        $keys = $this->filterPayload($table, $keys);
        $payload = $this->filterPayload($table, $payload);
        $query = DB::table($table);
        foreach ($keys as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        $existing = $query->first();
        if ($existing) {
            $updates = array_diff_key($payload, $keys);
            if ($updates !== []) {
                $query->update($updates);
            }

            return (int) $existing->{$primaryKey};
        }

        $insertPayload = $keys + $payload;
        if (isset(self::MANUAL_ID_TABLES[$table]) && ! array_key_exists($primaryKey, $insertPayload)) {
            $insertPayload[$primaryKey] = $this->nextManualId($table, $primaryKey);
            DB::table($table)->insert($insertPayload);

            return (int) $insertPayload[$primaryKey];
        }

        return (int) DB::table($table)->insertGetId($insertPayload, $primaryKey);
    }

    private function nextManualId(string $table, string $primaryKey): int
    {
        return ((int) DB::table($table)->max($primaryKey)) + 1;
    }

    private function printSummary(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('DEMO data seeded idempotently.');
        $this->command->line('Donor login: demo.donor001@example.test / ' . self::PASSWORD);
        $this->command->line('Protected admin/security/system snapshots unchanged.');
        $this->command->line('Generated data markers: DEMO, demo.donor###@example.test, DEMO-BR-####.');
    }
}
