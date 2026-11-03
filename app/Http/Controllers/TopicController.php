<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TopicController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', Rule::in(['all', 'system', 'private'])],
            'level' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'scope.*' => 'Оберіть коректний тип тем.',
            'level.*' => 'Рівень має бути текстом до 20 символів.',
            'search.*' => 'Пошук має бути текстом до 200 символів.',
            'page.*' => 'Номер сторінки має бути додатним цілим числом.',
        ]);

        $filters = [
            'scope' => $validated['scope'] ?? 'system',
            'level' => $request->query->has('level') ? ($validated['level'] ?? '') : 'A1-A2',
        ];
        $availableTopics = Topic::query()->visibleTo($request->user());
        $levels = (clone $availableTopics)->distinct()->orderBy('level')->pluck('level');
        $topics = $availableTopics->withCount('words');

        if ($filters['scope'] === 'system') {
            $topics->whereNull('topics.user_id');
        } elseif ($filters['scope'] === 'private') {
            $topics->whereNotNull('topics.user_id');
        }

        if ($filters['level'] !== '') {
            $topics->where('topics.level', $filters['level']);
        }

        $search = $validated['search'] ?? '';
        if ($search !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $topics->whereRaw("topics.name LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
        }
        $listFilters = $filters + ($search !== '' ? ['search' => $search] : []);

        return view('topics.index', [
            'topics' => $topics->orderBy('topics.id')->paginate(20)->appends($listFilters),
            'levels' => $levels,
            'filters' => $filters,
            'search' => $search,
            'listFilters' => $listFilters,
        ]);
    }

    public function show(Request $request, string $topic): View
    {
        $visibleTopic = Topic::query()->visibleTo($request->user())->withCount('words')->findOrFail($topic);
        $validated = $request->validate([
            'scope' => ['nullable', 'string', Rule::in(['all', 'system', 'private'])],
            'level' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:200'],
            'topics_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'scope.*' => 'Оберіть коректний тип тем.',
            'level.*' => 'Рівень має бути текстом до 20 символів.',
            'search.*' => 'Пошук має бути текстом до 200 символів.',
            'topics_page.*' => 'Номер сторінки тем має бути додатним цілим числом.',
            'page.*' => 'Номер сторінки має бути додатним цілим числом.',
        ]);

        $listContext = [
            'scope' => $validated['scope'] ?? 'all',
            'level' => $validated['level'] ?? '',
            'topics_page' => (int) ($validated['topics_page'] ?? 1),
        ];

        if (($validated['search'] ?? '') !== '') {
            $listContext['search'] = $validated['search'];
        }

        return view('topics.show', [
            'topic' => $visibleTopic,
            'words' => $visibleTopic->words()->orderBy('words.word_en')->orderBy('words.id')
                ->paginate(30, ['words.id', 'words.word_en', 'words.word_uk'])->appends($listContext),
            'backUrl' => route('topics.index', [
                'scope' => $listContext['scope'],
                'level' => $listContext['level'],
                'page' => $listContext['topics_page'],
                ...isset($listContext['search']) ? ['search' => $listContext['search']] : [],
            ]),
        ]);
    }
}
