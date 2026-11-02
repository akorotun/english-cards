<?php

namespace App\Http\Controllers;

use App\ActiveLearningProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, ActiveLearningProfile $activeProfile): RedirectResponse|View
    {
        $hadSelection = $request->session()->has(ActiveLearningProfile::SESSION_KEY);
        $profile = $activeProfile->current($request);

        if ($profile !== null) {
            return view('auth.ready', ['profile' => $profile]);
        }

        if ($hadSelection) {
            return redirect()->route('profiles.index')->with('status', 'Активний профіль більше не доступний. Виберіть профіль зі списку.');
        }

        $profiles = $request->user()->learningProfiles()->limit(2)->get();

        if ($profiles->count() === 1) {
            $profile = $profiles->first();
            $activeProfile->activate($request, $profile);

            return view('auth.ready', ['profile' => $profile]);
        }

        return redirect()->route('profiles.index');
    }
}
