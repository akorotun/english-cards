<?php

namespace App;

use App\Models\User;
use Illuminate\Http\Request;

class VocabularyImportAccess
{
    public function isAdmin(Request $request): bool
    {
        $user = $request->user();
        $configuredEmails = config('admin.emails');

        if (! $user instanceof User || ! $user->hasVerifiedEmail() || ! is_string($configuredEmails) || trim($configuredEmails) === '') {
            return false;
        }

        $adminEmails = [];

        foreach (explode(',', $configuredEmails) as $configuredEmail) {
            $email = mb_strtolower(trim($configuredEmail));

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }

            $adminEmails[] = $email;
        }

        return in_array(mb_strtolower(trim($user->email)), $adminEmails, true);
    }

    public function authorize(Request $request): ?int
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasVerifiedEmail(), 403);

        $scope = $request->input('scope', 'private');

        if ($scope === 'private') {
            return $user->id;
        }

        abort_unless($scope === 'system' && $this->isAdmin($request), 403);

        return null;
    }
}
