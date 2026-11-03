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
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'scope.*' => 'Оберіть коректний тип тем.',
            'level.*' => 'Рівень має бути текстом до 20 символів.',
            'page.*' => 'Номер сторінки має бути додатним цілим числом.',
        ]);

        $filters = [
            'scope' => $validated['scope'] ?? 'all',
            'level' => $validated['level'] ?? '',
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

        return view('topics.index', [
            'topics' => $topics->orderBy('topics.id')->paginate(20)->appends($filters),
            'levels' => $levels,
            'filters' => $filters,
        ]);
    }
}
