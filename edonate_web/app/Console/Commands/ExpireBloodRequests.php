<?php

namespace App\Console\Commands;

use App\Models\BloodRequest;
use App\Services\AdminNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ExpireBloodRequests extends Command
{
    protected $signature = 'blood-requests:expire';

    protected $description = 'Expire open blood requests whose expiry time has passed.';

    public function handle(AdminNotificationService $notifications): int
    {
        if (! Schema::hasTable('blood_requests')
            || ! Schema::hasColumn('blood_requests', 'status')
            || ! Schema::hasColumn('blood_requests', 'expires_at')) {
            $this->warn('Blood request expiry fields are unavailable; no requests were changed.');

            return self::SUCCESS;
        }

        $expiredCount = 0;

        BloodRequest::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('request_id')
            ->chunkById(100, function ($requests) use ($notifications, &$expiredCount): void {
                foreach ($requests as $request) {
                    $expired = DB::transaction(function () use ($request): ?BloodRequest {
                        $locked = BloodRequest::query()
                            ->whereKey($request->request_id)
                            ->lockForUpdate()
                            ->first();

                        if (! $locked
                            || ! in_array((string) $locked->status, ['open', 'in_progress'], true)
                            || ! $locked->expires_at
                            || $locked->expires_at->isFuture()) {
                            return null;
                        }

                        $previousStatus = (string) $locked->status;
                        $locked->status = 'expired';
                        $locked->save();

                        if (Schema::hasTable('audit_logs')) {
                            DB::table('audit_logs')->insert([
                                'actor_admin_id' => null,
                                'actor_name' => 'System Scheduler',
                                'actor_role' => 'System',
                                'action_type' => 'blood_request_expired',
                                'module_type' => 'blood_requests',
                                'target_table' => 'blood_requests',
                                'target_id' => (int) $locked->request_id,
                                'description' => 'Expired blood request '.(string) $locked->request_reference.'.',
                                'ip_address' => null,
                                'result' => 'success',
                                'metadata' => json_encode([
                                    'previous_status' => $previousStatus,
                                    'new_status' => 'expired',
                                    'expires_at' => $locked->expires_at->toIso8601String(),
                                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                                'created_at' => now(),
                            ]);
                        }

                        return $locked;
                    });

                    if (! $expired) {
                        continue;
                    }

                    $expiredCount++;
                    try {
                        $notifications->createAdminEvent(
                            'blood_request_expired',
                            'Blood Request Expired',
                            'Blood request '.(string) $expired->request_reference.' expired automatically.',
                            'blood_request',
                            (int) $expired->request_id
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            }, 'request_id');

        $this->info("Expired {$expiredCount} blood request(s).");

        return self::SUCCESS;
    }
}
