<?php

namespace App\Http\Controllers;

use App\ActiveLearningProfile;
use App\CheckProgressSync;
use App\Models\LearningProfile;
use App\Models\Topic;
use App\Models\Word;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LearningController extends Controller
{
    public function __construct(private ActiveLearningProfile $activeProfile) {}

    public function index(Request $request): RedirectResponse|View
    {
        $profile = $this->activeProfile->current($request);

        if ($profile === null) {
            return $this->chooseProfile();
        }

        return $this->selectionView($request, $profile);
    }

    public function selection(Request $request): RedirectResponse|View
    {
        $profile = $this->activeProfile->current($request);

        if ($profile === null) {
            return $this->chooseProfile();
        }

        $validator = Validator::make($request->all(), [
            'topic_ids' => ['required', 'array', 'list', 'min:1'],
            'topic_ids.*' => ['bail', 'required', 'integer', 'min:1', 'distinct'],
            'needs_review' => ['sometimes', 'boolean'],
        ], [
            'topic_ids.required' => 'Оберіть хоча б одну доступну тему.',
            'topic_ids.*' => 'Оберіть теми зі списку доступних.',
            'topic_ids.*.*' => 'Оберіть різні теми зі списку доступних.',
            'needs_review.*' => 'Оберіть коректне значення фільтра «Не показувати вивчені слова».',
        ]);

        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route($request->routeIs('check.*') ? 'check.index' : 'learning.index'));
        }

        $validated = $validator->validated();

        $selectedTopics = Topic::query()->visibleTo($request->user())
            ->whereIn('topics.id', $validated['topic_ids'])->orderBy('topics.id')->get();

        if ($selectedTopics->count() !== count($validated['topic_ids'])) {
            throw ValidationException::withMessages([
                'topic_ids' => 'Одна або кілька тем недоступні. Оновіть вибір тем.',
            ])->redirectTo(route($request->routeIs('check.*') ? 'check.index' : 'learning.index'));
        }

        $words = Word::query()->whereHas('topics', function (Builder $query) use ($request, $selectedTopics): void {
            $query->visibleTo($request->user())->whereIn('topics.id', $selectedTopics->modelKeys());
        });
        $needsReview = (bool) ($validated['needs_review'] ?? false);

        if ($needsReview) {
            $words->whereDoesntHave('progress', function (Builder $query) use ($profile): void {
                $query->where('learning_profile_id', $profile->id)->where('knowledge_level', 3);
            });
        }

        $cards = $words->distinct()->orderBy('words.id')->get(['words.id', 'words.word_en', 'words.word_uk']);

        if ($request->routeIs('learning.cards', 'check.start') && $cards->isNotEmpty()) {
            if ($request->routeIs('check.start') && $cards->count() > CheckProgressSync::MAX_WORDS) {
                throw ValidationException::withMessages(['topic_ids' => 'Для однієї перевірки оберіть теми загалом до 10 000 унікальних слів.'])
                    ->redirectTo(route('check.index'));
            }

            return view('learning.cards', [
                'selectedTopics' => $selectedTopics,
                'words' => $cards,
                'needsReview' => $needsReview,
                'isCheck' => $request->routeIs('check.start'),
                'profile' => $profile,
            ]);
        }

        return $this->selectionView($request, $profile, $selectedTopics, $cards, $needsReview);
    }

    private function chooseProfile(): RedirectResponse
    {
        return redirect()->route('profiles.index')
            ->with('status', 'Для навчання виберіть доступний навчальний профіль.');
    }

    /**
     * @param  Collection<int, Topic>|null  $selectedTopics
     * @param  Collection<int, Word>|null  $words
     */
    private function selectionView(
        Request $request,
        LearningProfile $profile,
        ?Collection $selectedTopics = null,
        ?Collection $words = null,
        bool $needsReview = false,
    ): View {
        return view('learning.index', [
            'profile' => $profile,
            'topics' => Topic::query()->visibleTo($request->user())->withCount('words')
                ->withExists(['words as has_learned_words' => function (Builder $query) use ($profile): void {
                    $query->whereHas('progress', function (Builder $query) use ($profile): void {
                        $query->where('learning_profile_id', $profile->id)->where('knowledge_level', 3);
                    });
                }])->orderBy('topics.id')->get(),
            'selectedTopics' => $selectedTopics,
            'words' => $words,
            'needsReview' => $needsReview,
            'isCheck' => $request->routeIs('check.*'),
            'checkStarted' => $request->routeIs('check.start') && $words?->isNotEmpty(),
        ]);
    }
}
