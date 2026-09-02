<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an authorized registered device approves a pending admin login.
 *
 * The challenge id is a cryptographically random, short-lived opaque value.
 * It is used as the private channel namespace and does not contain an admin
 * id, email address, or other account information.
 */
class LoginApprovedEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly string $challengeId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin-mfa.'.$this->challengeId)];
    }

    public function broadcastAs(): string
    {
        return 'LoginApproved';
    }

    public function broadcastWith(): array
    {
        return [
            'approved' => true,
            'challenge' => $this->challengeId,
        ];
    }
}
