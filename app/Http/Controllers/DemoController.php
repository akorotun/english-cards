<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\Word;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DemoController extends Controller
{
    public function index(): View
    {
        return $this->view();
    }

    public function selection(Request $request): View
    {
        $validator = Validator::make($request->all(), [
            'topic_ids' => ['required', 'array', 'list', 'min:1'],
            'topic_ids.*' => ['bail', 'required', 'integer', 'min:1', 'distinct'],
        ], ['topic_ids.required' => 'Оберіть хоча б одну доступну тему.']);

        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('demo.index'));
        }

        $ids = $validator->validated()['topic_ids'];
        $selectedTopics = Topic::query()->whereNull('user_id')->whereIn('id', $ids)->orderBy('id')->get();

        if ($selectedTopics->count() !== count($ids)) {
            throw ValidationException::withMessages(['topic_ids' => 'Одна або кілька тем недоступні. Оновіть вибір тем.'])
                ->redirectTo(route('demo.index'));
        }

        $words = Word::query()->whereHas('topics', function (Builder $query) use ($selectedTopics): void {
            $query->whereNull('topics.user_id')->whereIn('topics.id', $selectedTopics->modelKeys());
        })->distinct()->orderBy('words.id')->get(['words.id', 'words.word_en', 'words.word_uk']);

        return $this->view($selectedTopics, $words, $request->route('mode'));
    }

    /**
     * @param  Collection<int, Topic>|null  $selectedTopics
     * @param  Collection<int, Word>|null  $words
     */
    private function view(?Collection $selectedTopics = null, ?Collection $words = null, ?string $mode = null): View
    {
        return view('learning.index', [
            'isDemo' => true,
            'isCheck' => false,
            'topics' => Topic::query()->whereNull('user_id')->withCount('words')->orderBy('id')->get(),
            'selectedTopics' => $selectedTopics,
            'words' => $words,
            'needsReview' => false,
            'checkStarted' => false,
            'demoMode' => $mode,
        ]);
    }
}
