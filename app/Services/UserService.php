<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function getProfile(User $user): array
    {
        return $this->formatUser($user);
    }

    public function updateProfile(User $user, array $data): array
    {
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $data['updated_by'] = $user->id;
        $user->update($data);

        return $this->formatUser($user->fresh());
    }

    public function uploadAvatar(User $user, UploadedFile $file): array
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $file->store('avatars', 'public');
        $user->update(['avatar' => $path, 'updated_by' => $user->id]);

        return $this->formatUser($user->fresh());
    }

    public function deleteAccount(User $user): void
    {
        $user->update(['deleted_by' => $user->id]);
        $user->tokens()->delete();
        $user->delete(); // soft delete
    }

    /**
     * Restaurer un compte supprimé — appelé par l'admin ou le support
     */
    public function restoreAccount(int $userId): array
    {
        $user = User::withTrashed()->findOrFail($userId);

        if (!$user->trashed()) {
            throw ValidationException::withMessages([
                'user' => ['Ce compte n\'est pas supprimé.'],
            ]);
        }

        $user->restore();
        $user->update(['deleted_by' => null]);

        return $this->formatUser($user->fresh());
    }

    public function formatUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'avatar'            => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'adress'            => $user->adress,
            'preferences'       => $user->preferences,
            'is_verified'       => $user->is_verified,
            'is_suscribed'      => $user->is_suscribed,
            'email_verified_at' => $user->email_verified_at,
            'subscription_end'  => $user->subscription_end,
            'roles'             => $user->getRoleNames(),
            'created_at'        => $user->created_at,
        ];
    }
}