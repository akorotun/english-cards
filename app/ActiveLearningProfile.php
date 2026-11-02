<?php

namespace App;

use App\Models\LearningProfile;
use Illuminate\Http\Request;

class ActiveLearningProfile
{
    public const SESSION_KEY = 'active_learning_profile_id';

    public function current(Request $request): ?LearningProfile
    {
        $profileId = $request->session()->get(self::SESSION_KEY);

        if ($profileId === null) {
            return null;
        }

        if (! is_int($profileId) || $profileId < 1) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $profile = $request->user()->learningProfiles()->find($profileId);

        if ($profile === null) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return $profile;
    }

    public function activate(Request $request, LearningProfile $profile): void
    {
        abort_unless($profile->user_id === $request->user()->id, 404);

        $request->session()->put(self::SESSION_KEY, $profile->id);
    }
}
