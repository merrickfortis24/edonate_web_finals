<?php

namespace App\Services;

use App\Models\DonationEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class EventPostPublisher
{
    public function __construct(private readonly AppointmentBookingService $bookingService)
    {
    }

    public function sync(DonationEvent $event): void
    {
        $requiredColumns = [
            'id', 'type', 'author', 'author_avatar', 'author_badge', 'content',
            'image', 'likes', 'blood_type', 'hospital', 'event_date',
            'event_location', 'created_at', 'event_id',
        ];

        if (! Schema::hasTable('posts') || ! Schema::hasColumns('posts', $requiredColumns)) {
            throw new LogicException(
                'The posts table must exist and the event-post relationship migration must be applied before publishing donation events.'
            );
        }

        $existingPost = DB::table('posts')
            ->where('event_id', $event->event_id)
            ->lockForUpdate()
            ->first();

        $date = Carbon::parse($event->event_date);
        $dateLabel = $date->format('D, F j, Y');
        $timeLabel = $this->timeRange($event);
        $location = trim((string) $event->location_name);
        $address = trim((string) ($event->address ?? ''));
        $capacity = max(0, (int) $event->max_capacity);
        $status = $this->bookingService->normalizeEventStatus((string) $event->status);
        $statusLabel = ucfirst($status);
        $content = implode("\n", array_filter([
            '🩸 ' . trim((string) $event->title),
            '📅 Date: ' . $dateLabel,
            $timeLabel !== '' ? '🕒 Time: ' . $timeLabel : null,
            '📍 Location: ' . $location,
            $address !== '' ? 'Address: ' . $address : null,
            '👥 Capacity: ' . number_format($capacity) . ' donor' . ($capacity === 1 ? '' : 's'),
            'Status: ' . $statusLabel,
            'Book appointment: ' . route('donor.book-appointment', ['event_id' => (int) $event->event_id]),
            'Booking is available only to eligible donors while this event is open and has capacity.',
        ]));

        $post = [
            'event_id' => (int) $event->event_id,
            'type' => 'event',
            'author' => 'eDonate',
            'author_avatar' => url('/images/edonate-logo.png'),
            'author_badge' => 'Official Event',
            'content' => $content,
            'image' => null,
            'blood_type' => null,
            'hospital' => null,
            'event_date' => $dateLabel . ($timeLabel !== '' ? ' ' . $timeLabel : ''),
            'event_location' => $location,
        ];

        if ($existingPost) {
            DB::table('posts')->where('id', $existingPost->id)->update($post);

            return;
        }

        DB::table('posts')->insert($post + [
            'likes' => 0,
            'created_at' => now(),
        ]);
    }

    private function timeRange(DonationEvent $event): string
    {
        $start = $event->start_time ? Carbon::parse($event->start_time)->format('g:i A') : null;
        $end = $event->end_time ? Carbon::parse($event->end_time)->format('g:i A') : null;

        if ($start && $end) {
            return $start . ' – ' . $end;
        }

        return $start ?: ($end ?: '');
    }
}
