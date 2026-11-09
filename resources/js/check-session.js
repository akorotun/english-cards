import { speakEnglish } from './learning-session.js';

export function shuffled(items, random = Math.random) {
    const result = [...items];
    for (let i = result.length - 1; i > 0; i--) {
        const j = Math.floor(random() * (i + 1));
        [result[i], result[j]] = [result[j], result[i]];
    }
    return result;
}

export function createCheckState({ uuid, userId, profileId, cards }, random = Math.random) {
    if (!uuid || !userId || !profileId || cards.length === 0 || new Set(cards.map((card) => card.id)).size !== cards.length) {
        throw new Error('Invalid check session');
    }
    return {
        session_uuid: uuid, user_id: userId, learning_profile_id: profileId,
        status: 'in_progress', created_at: Date.now(), cards, word_ids: cards.map((card) => card.id),
        first_order: shuffled(cards.map((card) => card.id), random), repeat_order: [],
        round: 1, position: 0, answers: [],
    };
}

export function nextCheckState(state, answer, random = Math.random) {
    if (state.status !== 'in_progress' || !['know', 'dont_know'].includes(answer)) throw new Error('Invalid answer');
    const order = state.round === 1 ? state.first_order : state.repeat_order;
    const wordId = order[state.position];
    if (wordId === undefined) throw new Error('Invalid position');
    const next = { ...state, answers: state.answers.map((entry) => ({ ...entry })) };
    if (state.round === 1) {
        if (next.answers.some((entry) => entry.word_id === wordId)) throw new Error('Duplicate answer');
        next.answers.push({ word_id: wordId, first: answer });
    } else {
        const entry = next.answers.find((item) => item.word_id === wordId);
        if (!entry || entry.first !== 'dont_know' || entry.repeat) throw new Error('Invalid repeat');
        entry.repeat = answer;
    }
    next.position++;
    if (next.position === order.length) {
        if (state.round === 1) {
            next.repeat_order = shuffled(next.answers.filter((entry) => entry.first === 'dont_know').map((entry) => entry.word_id), random);
        }
        if (state.round === 1 && next.repeat_order.length) {
            next.round = 2;
            next.position = 0;
        } else {
            next.status = 'ready_to_sync';
        }
    }
    return next.status === 'ready_to_sync' ? freezeCheckState(next) : next;
}

export function freezeCheckState(state) {
    if (state.sync_payload) return state;
    const status = state.status === 'ready_to_sync' ? 'completed' : 'interrupted';
    return {
        ...state, status: status === 'completed' ? 'ready_to_sync' : 'interrupted',
        sync_payload: {
            session_uuid: state.session_uuid, user_id: state.user_id,
            learning_profile_id: state.learning_profile_id, status,
            word_ids: [...state.first_order], answers: state.answers.map((answer) => ({ ...answer })),
        },
    };
}

export function checkResults(answers) {
    const unknownIds = new Set(answers.filter((answer) => answer.repeat === 'dont_know').map((answer) => answer.word_id));
    const firstKnown = answers.filter((answer) => answer.first === 'know').length;
    return {
        checked: answers.length,
        firstKnown,
        neededRepeat: answers.length - firstKnown,
        remembered: answers.filter((answer) => answer.repeat === 'know').length,
        stillUnknown: unknownIds.size,
        unknownIds,
    };
}

export function openCheckStorage(indexedDB) {
    return new Promise((resolve, reject) => {
        if (!indexedDB) { reject(new Error('IndexedDB unavailable')); return; }
        let request;
        try { request = indexedDB.open('english-cards-check', 1); } catch (error) { reject(error); return; }
        request.onupgradeneeded = () => {
            const store = request.result.createObjectStore('sessions', { keyPath: 'session_uuid' });
            store.createIndex('owner_profile', ['user_id', 'learning_profile_id'], { unique: true });
        };
        request.onerror = () => reject(request.error);
        request.onblocked = () => reject(new Error('IndexedDB blocked'));
        request.onsuccess = () => resolve(request.result);
    });
}

export function writeCheckState(db, state, initial = false) {
    return new Promise((resolve, reject) => {
        let tx;
        try {
            tx = db.transaction('sessions', 'readwrite');
            const store = tx.objectStore('sessions');
            if (initial) store.add(state); else store.put(state);
        } catch (error) { reject(error); return; }
        tx.oncomplete = () => resolve();
        tx.onabort = () => reject(tx.error || new Error('Storage transaction aborted'));
        tx.onerror = () => {};
    });
}

export function createCheckController(initialState, persist, random = Math.random) {
    let state = initialState;
    let revealed = false;
    let busy = false;
    return {
        state: () => state,
        revealed: () => revealed,
        busy: () => busy,
        toggleTranslation: () => { if (!busy && state.status === 'in_progress') revealed = !revealed; },
        async answer(value) {
            if (busy || state.status !== 'in_progress') return false;
            busy = true;
            try {
                const next = nextCheckState(state, value, random);
                await persist(next);
                state = next;
                revealed = false;
                return true;
            } finally { busy = false; }
        },
        async interrupt() {
            if (busy || state.status !== 'in_progress') return false;
            busy = true;
            try {
                const next = freezeCheckState({ ...state, status: 'interrupted' });
                await persist(next);
                state = next;
                return true;
            } finally { busy = false; }
        },
    };
}

export async function initializeCheckSession(root, synchronization = null) {
    const error = root.querySelector('[data-check-error]');
    const content = root.querySelector('[data-check-content]');
    const cardScreen = root.querySelector('[data-check-card-screen]');
    const complete = root.querySelector('[data-check-complete]');
    const syncMessage = root.querySelector('[data-check-complete-sync]');
    const retrySync = root.querySelector('[data-check-complete-retry]');
    const restart = root.querySelector('[data-check-restart]');
    const reveal = root.querySelector('[data-check-reveal]');
    const answers = root.querySelector('[data-check-answers]');
    const audioFeedback = root.querySelector('[data-check-audio-feedback]');
    const stop = root.querySelector('[data-check-exit]');
    const dialog = root.querySelector('[data-check-dialog]');
    const dialogError = root.querySelector('[data-check-dialog-error]');
    const continueButton = root.querySelector('[data-check-continue]');
    const confirmButton = root.querySelector('[data-check-confirm]');
    const showError = (message) => { error.textContent = message; error.hidden = false; };
    const userId = Number(root.dataset.userId);
    const profileId = Number(root.dataset.profileId);
    let controller;
    let db;
    let synced = false;
    let syncing = false;
    let restarting = false;
    let exitAction;
    let exitTrigger;

    async function start(cards) {
        const state = createCheckState({ uuid: window.crypto.randomUUID(), userId, profileId, cards });
        await writeCheckState(db, state, true);
        controller = createCheckController(state, (next) => writeCheckState(db, next));
        synced = false;
    }

    try {
        db = synchronization?.db ?? await openCheckStorage(window.indexedDB);
        await start(JSON.parse(root.dataset.cards));
    } catch (exception) {
        showError(exception?.name === 'ConstraintError'
            ? 'Для цього профілю вже є незавершена або несинхронізована перевірка. Її відповіді залишаються в цьому браузері.'
            : 'Не вдалося відкрити локальне сховище. Перевірка не розпочалася; перевірте налаштування браузера та спробуйте знову.');
        return false;
    }

    const current = () => {
        const state = controller.state();
        const order = state.round === 1 ? state.first_order : state.repeat_order;
        return state.cards.find((card) => card.id === order[state.position]);
    };
    function render(resetAudio = true) {
        const state = controller.state();
        const finished = state.status === 'ready_to_sync' || state.status === 'interrupted';
        cardScreen.hidden = finished;
        complete.hidden = !finished;
        if (finished) {
            const results = checkResults(state.answers);
            const interrupted = state.status === 'interrupted';
            root.querySelector('[data-check-complete-title]').textContent = interrupted ? 'Перевірку перервано' : 'Перевірку завершено';
            root.querySelector('[data-check-complete-count]').textContent = results.checked;
            complete.querySelectorAll('[data-check-stat]').forEach((row) => {
                row.hidden = interrupted;
                row.querySelector('dd').textContent = results[row.dataset.checkStat];
            });
            restart.hidden = interrupted || results.stillUnknown === 0;
            restart.disabled = !synced;
            complete.focus();
            return;
        }
        const order = state.round === 1 ? state.first_order : state.repeat_order;
        root.querySelector('[data-check-round]').textContent = state.round === 1 ? 'Коло 1' : 'Коло 2 — повторення';
        root.querySelector('[data-check-progress]').max = order.length;
        root.querySelector('[data-check-progress]').value = state.position + 1;
        root.querySelector('[data-check-counter]').textContent = `${state.position + 1} / ${order.length}`;
        root.querySelector('[data-check-en]').textContent = current().word_en;
        const translation = root.querySelector('[data-check-uk]');
        translation.textContent = current().word_uk;
        translation.setAttribute('aria-hidden', String(!controller.revealed()));
        reveal.setAttribute('aria-expanded', String(controller.revealed()));
        answers.hidden = false;
        if (resetAudio) audioFeedback.hidden = true;
    }

    async function syncResult() {
        if (syncing || synced || controller.state().status === 'in_progress') return synced;
        syncing = true;
        retrySync.disabled = true;
        const retryFocused = document.activeElement === retrySync;
        syncMessage.textContent = 'Відповіді збережено в цьому браузері. Синхронізація…';
        try {
            const result = await synchronization?.sync(controller.state().session_uuid);
            synced = result?.synced ?? false;
            syncMessage.textContent = result?.message ?? 'Очікуємо підтвердження синхронізації.';
            retrySync.hidden = synced || !synchronization;
            restart.disabled = !synced;
            if (synced && retryFocused) (restart.hidden ? complete : restart).focus();
            return synced;
        } finally {
            syncing = false;
            retrySync.disabled = false;
        }
    }

    retrySync.addEventListener('click', syncResult);
    restart.addEventListener('click', async () => {
        const state = controller.state();
        if (restarting || !synced || state.status !== 'ready_to_sync') return;
        restarting = true;
        restart.disabled = true;
        error.hidden = true;
        try {
            const response = await window.fetch(root.dataset.restartUrl, {
                headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.timeout(15000),
            });
            if (response.redirected || [401, 403].includes(response.status)) {
                showError('Для нової перевірки поверніться до вибору тем і перевірте вхід та активний профіль.');
                return;
            }
            if (!response.ok) throw new Error('Could not reload cards');
            const currentSelection = await response.json();
            if (currentSelection.user_id !== userId || currentSelection.learning_profile_id !== profileId) {
                showError('Активний профіль змінився. Для нової перевірки поверніться до вибору тем.');
                return;
            }
            const { unknownIds } = checkResults(state.answers);
            const cards = currentSelection.cards.filter((card) => unknownIds.has(card.id));
            if (!cards.length) {
                showError('Картки для повторення більше не доступні у вибраних темах. Поверніться до вибору тем.');
                return;
            }
            if (synchronization?.pageClosed?.()) return;
            await start(cards);
            retrySync.hidden = true;
            render();
            reveal.focus();
        } catch {
            showError('Не вдалося розпочати повторення. Спробуйте ще раз або поверніться до вибору тем.');
        } finally {
            restarting = false;
            restart.disabled = !synced;
        }
    });

    reveal.addEventListener('click', () => { controller.toggleTranslation(); render(false); });
    answers.querySelectorAll('button').forEach((button) => button.addEventListener('click', async () => {
        if (controller.busy()) return;
        error.hidden = true;
        const pending = controller.answer(button.dataset.checkAnswer);
        answers.querySelectorAll('button').forEach((item) => { item.disabled = true; });
        try {
            if (await pending) {
                render();
                if (controller.state().status === 'in_progress') reveal.focus();
                else await syncResult();
            }
        } catch {
            showError('Не вдалося зберегти відповідь у браузері. Спробуйте ще раз; картку не змінено.');
        } finally { answers.querySelectorAll('button').forEach((item) => { item.disabled = false; }); }
    }));
    root.querySelector('[data-check-speak]').addEventListener('click', () => {
        audioFeedback.hidden = true;
        speakEnglish(current().word_en, window, () => {
            audioFeedback.textContent = 'Не вдалося відтворити вимову. Ви можете продовжити перевірку.';
            audioFeedback.hidden = false;
        });
    });
    function confirmExit(action, trigger) {
        if (controller.busy()) return;
        exitAction = action;
        exitTrigger = trigger;
        dialogError.hidden = true;
        dialog.showModal();
    }
    stop.addEventListener('click', () => confirmExit(null, stop));
    document.addEventListener('click', (event) => {
        if (controller.state().status !== 'in_progress' || event.defaultPrevented
            || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('[data-header-menu] a[href], a.brand[href]');
        if (!link) return;
        event.preventDefault();
        confirmExit(() => window.location.assign(link.href), link.closest('details')?.querySelector('summary') ?? link);
    });
    document.addEventListener('submit', (event) => {
        if (controller.state().status !== 'in_progress' || event.defaultPrevented || !event.target.matches('.site-nav-logout')) return;
        event.preventDefault();
        confirmExit(() => event.target.submit(), event.target.closest('details').querySelector('summary'));
    });
    continueButton.addEventListener('click', () => {
        if (!controller.busy()) dialog.close();
    });
    dialog.addEventListener('cancel', (event) => {
        if (controller.busy()) event.preventDefault();
    });
    dialog.addEventListener('close', () => {
        if (controller.state().status === 'in_progress') exitTrigger?.focus();
    });
    confirmButton.addEventListener('click', async () => {
        if (controller.busy()) return;
        dialogError.hidden = true;
        const pending = controller.interrupt();
        confirmButton.disabled = true;
        continueButton.disabled = true;
        try {
            if (await pending) {
                const leave = exitAction;
                error.hidden = true;
                dialog.close();
                render();
                if (await syncResult() && !synchronization?.pageClosed?.()) leave?.();
            }
        } catch {
            dialogError.textContent = 'Не вдалося зберегти стан перевірки. Спробуйте ще раз.';
            dialogError.hidden = false;
        } finally {
            confirmButton.disabled = false;
            continueButton.disabled = false;
        }
    });
    window.addEventListener('beforeunload', (event) => {
        if (controller.state().status === 'in_progress' || restarting) { event.preventDefault(); event.returnValue = ''; }
    });
    render();
    content.hidden = false;
    return true;
}
