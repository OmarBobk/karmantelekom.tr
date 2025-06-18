<?php

namespace App\Listeners;

use App\Models\Cart;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;

class SyncCartAfterLogin
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected Request $request
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $oldSessionId = $this->request->input('old_session_id');
        $user = $event->user;

        if (!$oldSessionId) {
            Log::warning('No old session ID found during cart sync', [
                'user_id' => $user->id,
                'current_session' => Session::getId()
            ]);
            return;
        }

        Log::info('Syncing cart with old session ID', [
            'old_session_id' => $oldSessionId,
            'user_id' => $user->id
        ]);

        app(\App\Services\CartService::class)->syncGuestCartToUser($user->id, $oldSessionId);
    }
}
