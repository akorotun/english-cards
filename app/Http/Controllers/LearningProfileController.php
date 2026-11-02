<?php

namespace App\Http\Controllers;

use App\ActiveLearningProfile;
use App\Models\LearningProfile;
use App\ThemePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearningProfileController extends Controller
{
    public function index(Request $request, ActiveLearningProfile $activeProfile): View
    {
        return view('profiles.index', [
            'profiles' => $request->user()->learningProfiles()->orderBy('id')->get(),
            'activeProfile' => $activeProfile->current($request),
        ]);
    }

    public function store(Request $request, ActiveLearningProfile $activeProfile): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $profile = $request->user()->learningProfiles()->create(['name' => $validated['name']]);

        if ($request->user()->learningProfiles()->count() === 1) {
            $activeProfile->activate($request, $profile);
        }

        return redirect()->route('profiles.show', $profile)->with('status', 'Профіль створено.');
    }

    public function show(Request $request, string $profile, ActiveLearningProfile $activeProfile): View
    {
        $ownedProfile = $this->ownedProfile($request, $profile);

        return view('profiles.show', [
            'profile' => $ownedProfile,
            'isActive' => $activeProfile->current($request)?->is($ownedProfile) ?? false,
            'themeLabel' => match ($ownedProfile->theme_preference) {
                ThemePreference::System => 'Системна',
                ThemePreference::Light => 'Світла',
                ThemePreference::Dark => 'Темна',
            },
        ]);
    }

    public function update(Request $request, string $profile): RedirectResponse
    {
        $ownedProfile = $this->ownedProfile($request, $profile);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $ownedProfile->update(['name' => $validated['name']]);

        return redirect()->route('profiles.show', $ownedProfile)->with('status', 'Назву профілю оновлено.');
    }

    public function updateTheme(Request $request, string $profile, ActiveLearningProfile $activeProfile): RedirectResponse|JsonResponse
    {
        $ownedProfile = $this->ownedProfile($request, $profile);
        abort_unless($activeProfile->current($request)?->is($ownedProfile), 404);

        $validated = $request->validate([
            'theme_preference' => LearningProfile::themePreferenceRules(),
        ]);

        $ownedProfile->update(['theme_preference' => $validated['theme_preference']]);

        if ($request->expectsJson()) {
            return response()->json(['theme_preference' => $ownedProfile->theme_preference->value]);
        }

        return redirect()->back()->with('status', 'Тему оформлення оновлено.');
    }

    public function activate(Request $request, string $profile, ActiveLearningProfile $activeProfile): RedirectResponse|JsonResponse
    {
        $ownedProfile = $this->ownedProfile($request, $profile);
        $activeProfile->activate($request, $ownedProfile);

        if ($request->expectsJson()) {
            return response()->json([
                'profile_id' => $ownedProfile->id,
                'theme_preference' => $ownedProfile->theme_preference->value,
                'theme_url' => route('profiles.theme.update', $ownedProfile),
            ]);
        }

        return redirect()->route('home');
    }

    private function ownedProfile(Request $request, string $profile): LearningProfile
    {
        return $request->user()->learningProfiles()->findOrFail($profile);
    }
}
