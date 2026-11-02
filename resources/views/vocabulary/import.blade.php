@extends('layouts.app')

@section('title', 'Імпорт слів — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <p class="eyebrow">Словник</p>
        <h1 id="page-title">Імпорт слів</h1>
        <p class="intro">Завантажте CSV, перевірте результат і лише потім підтвердіть імпорт.</p>

        @if (session('import_error'))
            <p class="notice import-error" role="alert">{{ session('import_error') }}</p>
        @endif
        @if ($errors->any())
            <div class="notice import-error" role="alert">
                <strong>Файл не оброблено:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('vocabulary.import.preview') }}" enctype="multipart/form-data" class="form-stack" data-import-form data-loading-label="Перевіряємо файл…">
            @csrf
            <div class="field">
                <label for="import-file">CSV-файл</label>
                <input id="import-file" type="file" name="file" accept=".csv,text/csv" required aria-describedby="import-rules" aria-invalid="{{ $errors->has('file') ? 'true' : 'false' }}">
                <p id="import-rules" class="hint">UTF-8, кома як роздільник, до 1 МіБ і 2 000 рядків даних. Обов'язкові колонки: <code>word_en</code>, <code>word_uk</code>, <code>level</code>, <code>topic</code>. Колонка <code>lesson</code> допускається, але ігнорується.</p>
            </div>

            @if ($isAdmin)
                <fieldset class="import-scope">
                    <legend>Тип імпорту</legend>
                    <label><input type="radio" name="scope" value="private" @checked(old('scope', 'private') !== 'system')> Приватні теми</label>
                    <label><input type="radio" name="scope" value="system" @checked(old('scope') === 'system')> Системні теми</label>
                    <p class="hint">Системні теми будуть доступні всім користувачам.</p>
                </fieldset>
            @else
                <input type="hidden" name="scope" value="private">
                <p class="hint">Слова буде додано лише до ваших приватних тем.</p>
            @endif

            <button class="button button-primary" type="submit">Перевірити файл</button>
        </form>
    </section>

    @if ($preview)
        <section class="panel import-section" aria-labelledby="preview-title">
            <h2 id="preview-title">Попередній перегляд</h2>
            <p class="hint">Тип: <strong>{{ $preview['scope'] === 'system' ? 'Системні теми' : 'Приватні теми' }}</strong>. Дані ще не записано. Підтвердження діє 30 хвилин.</p>
            <dl class="import-stats">
                <div><dt>Рядків</dt><dd>{{ $preview['rows'] }}</dd></div>
                <div><dt>Тем</dt><dd>{{ $preview['topics'] }}</dd></div>
                <div><dt>Унікальних пар слів</dt><dd>{{ $preview['unique_pairs'] }}</dd></div>
                <div><dt>Зв'язків</dt><dd>{{ $preview['unique_links'] }}</dd></div>
                <div><dt>Нових тем</dt><dd>{{ $preview['new_topics'] }}</dd></div>
                <div><dt>Наявних тем</dt><dd>{{ $preview['reused_topics'] }}</dd></div>
                <div><dt>Потенційно нових зв'язків</dt><dd>{{ $preview['potential_new_links'] ?? '—' }}</dd></div>
                <div><dt>Наявних зв'язків</dt><dd>{{ $preview['existing_links'] }}</dd></div>
                <div><dt>Повторних рядків</dt><dd>{{ $preview['duplicate_rows'] }}</dd></div>
            </dl>

            @foreach ($preview['warnings'] as $warning)
                <p class="notice">{{ $warning }}</p>
            @endforeach
            @if ($preview['ambiguities'])
                <div class="notice import-error" role="alert">
                    <strong>Імпорт заблоковано через неоднозначні теми:</strong>
                    <ul>
                        @foreach ($preview['ambiguities'] as $ambiguity)
                            <li>Рядок {{ $ambiguity['row'] }}: «{{ $ambiguity['topic'] }}» ({{ $ambiguity['level'] }}). {{ $ambiguity['message'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($preview['can_import'] && $preview['token'])
                <form method="post" action="{{ route('vocabulary.import.confirm') }}" class="form-stack import-confirm" data-import-form data-loading-label="Імпортуємо…">
                    @csrf
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">
                    <input type="hidden" name="scope" value="{{ $preview['scope'] }}">
                    @if ($preview['scope'] === 'system')
                        <div class="notice import-system-warning">
                            <label><input type="checkbox" name="system_confirmation" value="1" required> Підтверджую імпорт системних тем, доступних усім користувачам.</label>
                        </div>
                    @endif
                    <button class="button button-primary" type="submit">Імпортувати</button>
                </form>
            @endif
        </section>
    @endif

    @if ($report)
        <section class="panel import-section" aria-labelledby="report-title" role="status">
            <h2 id="report-title">Імпорт завершено</h2>
            <p class="hint">Тип: <strong>{{ $report['scope'] === 'system' ? 'Системні теми' : 'Приватні теми' }}</strong></p>
            <dl class="import-stats">
                <div><dt>Опрацьовано рядків</dt><dd>{{ $report['rows'] }}</dd></div>
                <div><dt>Тем</dt><dd>{{ $report['topics'] }}</dd></div>
                <div><dt>Унікальних пар слів</dt><dd>{{ $report['unique_pairs'] }}</dd></div>
                <div><dt>Зв'язків</dt><dd>{{ $report['unique_links'] }}</dd></div>
                <div><dt>Нових тем</dt><dd>{{ $report['new_topics'] }}</dd></div>
                <div><dt>Наявних тем</dt><dd>{{ $report['reused_topics'] }}</dd></div>
                <div><dt>Нових слів</dt><dd>{{ $report['new_words'] }}</dd></div>
                <div><dt>Наявних слів</dt><dd>{{ $report['reused_words'] }}</dd></div>
                <div><dt>Нових зв'язків</dt><dd>{{ $report['new_links'] }}</dd></div>
                <div><dt>Наявних зв'язків</dt><dd>{{ $report['reused_links'] }}</dd></div>
                <div><dt>Пропущено повторних рядків</dt><dd>{{ $report['duplicate_rows'] }}</dd></div>
            </dl>
        </section>
    @endif
@endsection
