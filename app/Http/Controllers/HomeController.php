<?php

namespace App\Http\Controllers;

use App\ActiveLearningProfile;
use App\Models\Word;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, ActiveLearningProfile $activeProfile): RedirectResponse|View
    {
        $hadSelection = $request->session()->has(ActiveLearningProfile::SESSION_KEY);
        $profile = $activeProfile->current($request);

        if ($profile === null && $hadSelection) {
            return redirect()->route('profiles.index')->with('status', 'Активний профіль більше не доступний. Виберіть профіль зі списку.');
        }

        if ($profile === null) {
            $profiles = $request->user()->learningProfiles()->limit(2)->get();

            if ($profiles->count() !== 1) {
                return redirect()->route('profiles.index');
            }

            $profile = $profiles->first();
            $activeProfile->activate($request, $profile);
        }

        $statistics = Word::query()
            ->whereHas('topics', fn (Builder $query) => $query->visibleTo($request->user()))
            ->leftJoin('word_progress', function (JoinClause $join) use ($profile): void {
                $join->on('word_progress.word_id', '=', 'words.id')
                    ->where('word_progress.learning_profile_id', '=', $profile->id);
            })
            ->selectRaw('COUNT(*) as available, COALESCE(SUM(CASE WHEN word_progress.knowledge_level = 3 THEN 1 ELSE 0 END), 0) as learned, COALESCE(SUM(CASE WHEN word_progress.knowledge_level IN (1, 2) THEN 1 ELSE 0 END), 0) as in_progress')
            ->first();

        return view('auth.ready', ['profile' => $profile, 'statistics' => $statistics]);
    }
}
