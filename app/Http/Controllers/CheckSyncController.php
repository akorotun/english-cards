<?php

namespace App\Http\Controllers;

use App\CheckProgressSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckSyncController extends Controller
{
    public function __invoke(Request $request, CheckProgressSync $sync): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 1024 * 1024, 413);

        $payload = Validator::make(['batch' => $request->all()], [
            'batch' => ['required', 'array:session_uuid,user_id,learning_profile_id,status,word_ids,answers'],
            'batch.session_uuid' => ['required', 'uuid'],
            'batch.user_id' => ['required', 'integer', 'min:1'],
            'batch.learning_profile_id' => ['required', 'integer', 'min:1'],
            'batch.status' => ['required', Rule::in(['completed', 'interrupted'])],
            'batch.word_ids' => ['required', 'array', 'list', 'min:1', 'max:'.CheckProgressSync::MAX_WORDS],
            'batch.word_ids.*' => ['required', 'integer', 'min:1', 'max:9007199254740991', 'distinct'],
            'batch.answers' => ['present', 'array', 'list', 'max:'.CheckProgressSync::MAX_WORDS],
            'batch.answers.*' => ['required', 'array:word_id,first,repeat'],
            'batch.answers.*.word_id' => ['required', 'integer', 'min:1', 'max:9007199254740991', 'distinct'],
            'batch.answers.*.first' => ['required', Rule::in(['know', 'dont_know'])],
            'batch.answers.*.repeat' => ['sometimes', 'required', Rule::in(['know', 'dont_know'])],
        ])->validate()['batch'];

        abort_unless((int) $payload['user_id'] === $request->user()->id, 403);
        $profile = $request->user()->learningProfiles()->findOrFail($payload['learning_profile_id']);
        $payload['session_uuid'] = strtolower($payload['session_uuid']);
        $payload['user_id'] = (int) $payload['user_id'];
        $payload['learning_profile_id'] = (int) $payload['learning_profile_id'];
        $payload['word_ids'] = array_map(intval(...), $payload['word_ids']);
        $payload['answers'] = array_map(static function (array $answer): array {
            return ['word_id' => (int) $answer['word_id'], 'first' => $answer['first']]
                + (isset($answer['repeat']) ? ['repeat' => $answer['repeat']] : []);
        }, $payload['answers']);

        $allAnswered = count($payload['answers']) === count($payload['word_ids']);

        foreach ($payload['answers'] as $position => $answer) {
            if ($answer['word_id'] !== ($payload['word_ids'][$position] ?? null)
                || (isset($answer['repeat']) && ($answer['first'] !== 'dont_know' || ! $allAnswered))
                || ($payload['status'] === 'completed' && $answer['first'] === 'dont_know' && ! isset($answer['repeat']))) {
                throw ValidationException::withMessages(['answers' => 'Некоректний порядок відповідей або повторення.']);
            }
        }

        if ($payload['status'] === 'completed' && ! $allAnswered) {
            throw ValidationException::withMessages(['answers' => 'Завершена перевірка має містити всі відповіді.']);
        }

        return response()->json($sync->apply($profile, $payload));
    }
}
