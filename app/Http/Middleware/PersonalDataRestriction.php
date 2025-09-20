<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use App\Models\Message;
use App\Models\Channel;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PersonalDataRestriction
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Must be authenticated
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json(['error' => 'Access denied: Account is inactive'], 403);
        }

        // Rate limiting for non-admin users
        if (!$user->is_admin && $this->isRateLimited($user)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        // Extract resource information from the route
        $routeParameters = $request->route()->parameters();
        
        // Handle different resource types
        foreach ($routeParameters as $key => $value) {
            if ($value instanceof Message) {
                if (!$this->canAccessMessage($user, $value, $request)) {
                    return response()->json(['error' => 'Access denied: You can only access your own messages'], 403);
                }
            } elseif ($key === 'message' && is_numeric($value)) {
                // Handle route parameter that's not yet resolved to a model
                $message = Message::with('channel')->find($value);
                if ($message && !$this->canAccessMessage($user, $message, $request)) {
                    return response()->json(['error' => 'Access denied: You can only access your own messages'], 403);
                }
            } elseif ($value instanceof Channel) {
                if (!$this->canAccessChannel($user, $value, $request)) {
                    if ($value->is_dm) {
                        return response()->json(['error' => 'Access denied: You are not a participant in this DM'], 403);
                    } else {
                        return response()->json(['error' => 'Access denied: You cannot access this channel'], 403);
                    }
                }
            } elseif ($key === 'channel' && is_numeric($value)) {
                // Handle route parameter that's not yet resolved to a model
                $channel = Channel::find($value);
                if ($channel && !$this->canAccessChannel($user, $channel, $request)) {
                    if ($channel->is_dm) {
                        return response()->json(['error' => 'Access denied: You are not a participant in this DM'], 403);
                    } else {
                        return response()->json(['error' => 'Access denied: You cannot access this channel'], 403);
                    }
                }
            } elseif ($value instanceof User && $key === 'user') {
                if (!$this->canAccessUserData($user, $value, $request)) {
                    return response()->json(['error' => 'Access denied: You can only access your own data'], 403);
                }
            } elseif ($key === 'user' && is_numeric($value)) {
                // Handle route parameter that's not yet resolved to a model
                $targetUser = User::find($value);
                if ($targetUser && !$this->canAccessUserData($user, $targetUser, $request)) {
                    return response()->json(['error' => 'Access denied: You can only access your own data'], 403);
                }
            }
        }

        return $next($request);
    }

    /**
     * Check if user can access a specific message
     */
    private function canAccessMessage(User $user, Message $message, Request $request): bool
    {
        // Admin users can access any message (with audit logging)
        if ($user->is_admin) {
            $this->logAdminAccess($user, 'access_user_message', 'message', $message->id, $message->user_id, $request);
            return true;
        }

        // Regular users can only access their own messages
        if ($message->user_id === $user->id) {
            return true;
        }

        // Check if it's a DM and user is a participant
        if ($message->channel->is_dm && $this->isDmParticipant($user, $message->channel)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can access a specific channel
     */
    private function canAccessChannel(User $user, Channel $channel, Request $request): bool
    {
        // Admin users can access any channel (with audit logging)
        if ($user->is_admin) {
            if ($channel->is_dm) {
                $this->logAdminAccess($user, 'access_dm_channel', 'channel', $channel->id, null, $request);
            }
            return true;
        }

        // For DM channels, check if user is a participant
        if ($channel->is_dm) {
            return $this->isDmParticipant($user, $channel);
        }

        // For public channels, allow access
        if (!$channel->is_private) {
            return true;
        }

        // For private channels, check membership (would need channel_users relationship)
        return $this->isChannelMember($user, $channel);
    }

    /**
     * Check if user can access another user's data
     */
    private function canAccessUserData(User $currentUser, User $targetUser, Request $request): bool
    {
        // Users can always access their own data
        if ($currentUser->id === $targetUser->id) {
            return true;
        }

        // Admin users can access any user data (with audit logging)
        if ($currentUser->is_admin) {
            $justification = $request->header('X-Access-Justification', 'Admin accessed user data without explicit justification');
            $this->logAdminAccess($currentUser, 'access_user_data', 'user', $targetUser->id, $targetUser->id, $request, $justification);
            return true;
        }

        return false;
    }

    /**
     * Check if user is a participant in a DM channel
     */
    private function isDmParticipant(User $user, Channel $channel): bool
    {
        return $channel->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Check if user is a member of a channel
     */
    private function isChannelMember(User $user, Channel $channel): bool
    {
        // For now, assume users can access channels they have messages in
        // In a full implementation, you'd check the channel_users table
        return $channel->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Log admin access for audit purposes
     */
    private function logAdminAccess(User $admin, string $action, string $resourceType, int $resourceId, ?int $accessedUserId, Request $request, ?string $notes = null): void
    {
        AuditLog::create([
            'admin_user_id' => $admin->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'accessed_user_id' => $accessedUserId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => $notes,
            'metadata' => [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'headers' => $this->getSafeHeaders($request),
            ],
        ]);
    }

    /**
     * Get safe headers for audit logging (exclude sensitive data)
     */
    private function getSafeHeaders(Request $request): array
    {
        $headers = $request->headers->all();
        
        // Remove sensitive headers
        unset($headers['authorization'], $headers['cookie'], $headers['x-csrf-token']);
        
        return array_map(function($header) {
            return is_array($header) ? implode(', ', $header) : $header;
        }, $headers);
    }

    /**
     * Check if user is rate limited
     */
    private function isRateLimited(User $user): bool
    {
        $key = 'rate_limit_user_' . $user->id;
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= 5) { // 5 requests per minute
            return true;
        }
        
        Cache::put($key, $attempts + 1, 60); // 60 seconds
        return false;
    }
}
