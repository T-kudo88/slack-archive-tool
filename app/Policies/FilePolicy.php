<?php

namespace App\Policies;

use App\Models\SlackFile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FilePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any files.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the file.
     */
    public function view(User $user, SlackFile $file): bool
    {
        // Admin can view all files
        if ($user->role === 'admin') {
            return true;
        }

        // Public files can be viewed by anyone
        if ($file->is_public) {
            return true;
        }

        // Users can view their own files
        if ($file->user_id === $user->id) {
            return true;
        }

        // Users can view files in channels they have access to
        if ($file->channel && $user->hasAccessToChannel($file->channel)) {
            return true;
        }

        // Check if file is attached to a message the user can access
        if ($file->message && $user->hasAccessToMessage($file->message)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create files.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the file.
     */
    public function update(User $user, SlackFile $file): bool
    {
        // Admin can update all files
        if ($user->role === 'admin') {
            return true;
        }

        // Users can update their own files
        return $file->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the file.
     */
    public function delete(User $user, SlackFile $file): bool
    {
        // Admin can delete all files
        if ($user->role === 'admin') {
            return true;
        }

        // Users can delete their own files
        return $file->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the file.
     */
    public function restore(User $user, SlackFile $file): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the file.
     */
    public function forceDelete(User $user, SlackFile $file): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can download the file.
     */
    public function download(User $user, SlackFile $file): bool
    {
        return $this->view($user, $file);
    }

    /**
     * Determine whether the user can share the file publicly.
     */
    public function makePublic(User $user, SlackFile $file): bool
    {
        // Admin can make any file public
        if ($user->role === 'admin') {
            return true;
        }

        // Users can make their own files public
        return $file->user_id === $user->id;
    }

    /**
     * Determine whether the user can make the file private.
     */
    public function makePrivate(User $user, SlackFile $file): bool
    {
        // Admin can make any file private
        if ($user->role === 'admin') {
            return true;
        }

        // Users can make their own files private
        return $file->user_id === $user->id;
    }

    /**
     * Determine whether the user can view file statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return true; // Users can view their own statistics
    }

    /**
     * Determine whether the user can perform bulk operations.
     */
    public function bulkDelete(User $user): bool
    {
        return true; // Users can bulk delete their own files
    }
}