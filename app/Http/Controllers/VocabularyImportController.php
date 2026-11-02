<?php

namespace App\Http\Controllers;

use App\VocabularyImportAccess;
use App\VocabularyImporter;
use App\VocabularyImportUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class VocabularyImportController extends Controller
{
    public function index(Request $request, VocabularyImportAccess $access): View
    {
        $preview = $request->session()->get('import_preview');
        $isAdmin = $access->isAdmin($request);

        if ($request->session()->get('import_preview_user_id') !== $request->user()->id
            || ($preview !== null && $preview['scope'] === 'system' && ! $isAdmin)) {
            $request->session()->forget(['import_preview', 'import_preview_user_id']);
            $preview = null;
        }

        return view('vocabulary.import', [
            'isAdmin' => $isAdmin,
            'preview' => $preview,
            'report' => $request->session()->get('import_report'),
        ]);
    }

    public function preview(Request $request, VocabularyImportAccess $access, VocabularyImportUpload $upload): RedirectResponse
    {
        $access->authorize($request);
        $request->session()->forget(['import_preview', 'import_preview_user_id']);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:1024'],
        ], [
            'file.required' => 'Оберіть CSV-файл.',
            'file.file' => 'Не вдалося прочитати вибраний файл.',
            'file.max' => 'CSV-файл має бути не більшим за 1 МіБ.',
        ]);

        try {
            $summary = $upload->preview($request, $validated['file']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return to_route('vocabulary.import')->with('import_error', 'Не вдалося перевірити файл. Спробуйте ще раз.');
        }

        $request->session()->put('import_preview', $summary);
        $request->session()->put('import_preview_user_id', $request->user()->id);

        return to_route('vocabulary.import');
    }

    public function confirm(Request $request, VocabularyImportAccess $access, VocabularyImporter $importer): RedirectResponse
    {
        $access->authorize($request);
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        if ($request->input('scope', 'private') === 'system') {
            $request->validate([
                'system_confirmation' => ['accepted'],
            ], [
                'system_confirmation.accepted' => 'Підтвердіть імпорт системних тем, доступних усім користувачам.',
            ]);
        }

        try {
            $report = $importer->confirm($request, $validated['token']);
        } catch (ValidationException $exception) {
            $request->session()->forget(['import_preview', 'import_preview_user_id']);

            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return to_route('vocabulary.import')->with('import_error', 'Не вдалося завершити імпорт. Спробуйте перевірити файл іще раз; повторний імпорт не створює дублікатів.');
        }

        $request->session()->forget(['import_preview', 'import_preview_user_id']);

        return to_route('vocabulary.import')->with('import_report', $report);
    }
}
