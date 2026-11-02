<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VocabularyImportUpload
{
    private const DIRECTORY = 'vocabulary-imports';

    private const SESSION_KEY = 'vocabulary_import_uploads';

    private const LIFETIME_SECONDS = 1800;

    public function __construct(
        private VocabularyImportAccess $access,
        private VocabularyImportPreview $preview,
    ) {}

    /** @return array<string, mixed> */
    public function preview(Request $request, UploadedFile $file): array
    {
        $ownerId = $this->access->authorize($request);

        if (strtolower($file->getClientOriginalExtension()) !== 'csv' || ! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'Потрібен коректний файл із розширенням .csv.']);
        }

        $initialHash = hash_file('sha256', $file->getRealPath());

        if ($initialHash === false) {
            throw ValidationException::withMessages(['file' => 'Не вдалося прочитати CSV-файл.']);
        }

        $summary = $this->preview->inspect($request, $file->getRealPath());

        if (! $summary['can_import']) {
            return $summary + ['token' => null];
        }

        $token = bin2hex(random_bytes(24));
        $path = self::DIRECTORY.'/'.$token.'.csv';
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || ! hash_equals($initialHash, hash('sha256', $contents))) {
            throw ValidationException::withMessages(['file' => 'CSV-файл змінився під час перевірки. Перевірте його знову.']);
        }

        if (! Storage::disk('local')->put($path, $contents)) {
            throw ValidationException::withMessages(['file' => 'Не вдалося тимчасово зберегти CSV-файл.']);
        }

        $uploads = $request->session()->get(self::SESSION_KEY, []);
        $uploads[$token] = [
            'user_id' => $request->user()->id,
            'owner_id' => $ownerId,
            'scope' => $summary['scope'],
            'session_id' => $request->session()->getId(),
            'hash' => $initialHash,
            'created_at' => now()->timestamp,
        ];
        $request->session()->put(self::SESSION_KEY, $uploads);

        return $summary + ['token' => $token];
    }

    public function resolve(Request $request, string $token): string
    {
        $ownerId = $this->access->authorize($request);

        if (! preg_match('/\A[a-f0-9]{48}\z/D', $token)) {
            $this->invalidPreview();
        }

        $uploads = $request->session()->get(self::SESSION_KEY, []);
        $metadata = $uploads[$token] ?? null;
        $path = self::DIRECTORY.'/'.$token.'.csv';

        if (! is_array($metadata)
            || $metadata['user_id'] !== $request->user()->id
            || $metadata['owner_id'] !== $ownerId
            || $metadata['scope'] !== $request->input('scope', 'private')
            || $metadata['session_id'] !== $request->session()->getId()
            || now()->timestamp >= $metadata['created_at'] + self::LIFETIME_SECONDS
        ) {
            $this->invalidPreview();
        }

        $actualHash = Storage::disk('local')->exists($path)
            ? @hash_file('sha256', Storage::disk('local')->path($path))
            : false;

        if ($actualHash === false || ! hash_equals($metadata['hash'], $actualHash)) {
            $this->invalidPreview();
        }

        return Storage::disk('local')->path($path);
    }

    public function forget(Request $request, string $token): void
    {
        $uploads = $request->session()->get(self::SESSION_KEY, []);

        if (! isset($uploads[$token])
            || $uploads[$token]['user_id'] !== $request->user()->id
            || $uploads[$token]['session_id'] !== $request->session()->getId()) {
            return;
        }

        unset($uploads[$token]);
        $request->session()->put(self::SESSION_KEY, $uploads);

        if (preg_match('/\A[a-f0-9]{48}\z/D', $token)) {
            Storage::disk('local')->delete(self::DIRECTORY.'/'.$token.'.csv');
        }
    }

    public function cleanupExpired(): int
    {
        $removed = 0;
        $disk = Storage::disk('local');

        foreach ($disk->files(self::DIRECTORY) as $path) {
            if (str_ends_with($path, '.csv') && $disk->lastModified($path) <= now()->timestamp - self::LIFETIME_SECONDS) {
                if ($disk->delete($path)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    private function invalidPreview(): never
    {
        throw ValidationException::withMessages(['file' => 'Попередній перегляд недоступний або строк його дії минув. Перевірте файл знову.']);
    }
}
