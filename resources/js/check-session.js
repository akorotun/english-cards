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
        status: 'in_progress', cards, word_ids: cards.map((card) => card.id),
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
    return next;
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
        reveal: () => { if (!busy && state.status === 'in_progress') revealed = true; },
        async answer(value) {
            if (busy || !revealed || state.status !== 'in_progress') return false;
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
                const next = { ...state, status: 'interrupted' };
                await persist(next);
                state = next;
                return true;
            } finally { busy = false; }
        },
    };
}

export async function initializeCheckSession(root) {
    const error = root.querySelector('[data-check-error]');
    const content = root.querySelector('[data-check-content]');
    const cardScreen = root.querySelector('[data-check-card-screen]');
    const complete = root.querySelector('[data-check-complete]');
    const reveal = root.querySelector('[data-check-reveal]');
    const answers = root.querySelector('[data-check-answers]');
    const audioFeedback = root.querySelector('[data-check-audio-feedback]');
    const showError = (message) => { error.textContent = message; error.hidden = false; };
    let controller;
    try {
        const db = await openCheckStorage(window.indexedDB);
        const state = createCheckState({
            uuid: window.crypto.randomUUID(), userId: Number(root.dataset.userId),
            profileId: Number(root.dataset.profileId), cards: JSON.parse(root.dataset.cards),
        });
        await writeCheckState(db, state, true);
        controller = createCheckController(state, (next) => writeCheckState(db, next));
    } catch (exception) {
        showError(exception?.name === 'ConstraintError'
            ? 'Для цього профілю вже є незавершена або несинхронізована перевірка. Її відповіді залишаються в цьому браузері.'
            : 'Не вдалося відкрити локальне сховище. Перевірка не розпочалася; перевірте налаштування браузера та спробуйте знову.');
        return;
    }

    const current = () => {
        const state = controller.state();
        const order = state.round === 1 ? state.first_order : state.repeat_order;
        return state.cards.find((card) => card.id === order[state.position]);
    };
    function render() {
        const state = controller.state();
        const finished = state.status === 'ready_to_sync';
        cardScreen.hidden = finished;
        complete.hidden = !finished;
        if (finished) { complete.focus(); return; }
        const order = state.round === 1 ? state.first_order : state.repeat_order;
        root.querySelector('[data-check-round]').textContent = state.round === 1 ? 'Коло 1' : 'Коло 2 — повторення';
        root.querySelector('[data-check-progress]').max = order.length;
        root.querySelector('[data-check-progress]').value = state.position + 1;
        root.querySelector('[data-check-counter]').textContent = `${state.position + 1} / ${order.length}`;
        root.querySelector('[data-check-en]').textContent = current().word_en;
        const translation = root.querySelector('[data-check-uk]');
        translation.textContent = controller.revealed() ? current().word_uk : '';
        translation.hidden = !controller.revealed();
        reveal.hidden = controller.revealed();
        answers.hidden = !controller.revealed();
        audioFeedback.hidden = true;
    }
    reveal.addEventListener('click', () => { controller.reveal(); render(); if (controller.revealed()) answers.querySelector('button').focus(); });
    answers.querySelectorAll('button').forEach((button) => button.addEventListener('click', async () => {
        if (controller.busy()) return;
        error.hidden = true;
        const pending = controller.answer(button.dataset.checkAnswer);
        answers.querySelectorAll('button').forEach((item) => { item.disabled = true; });
        try {
            if (await pending) { render(); if (controller.state().status === 'in_progress') reveal.focus(); }
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
    document.querySelector('[data-check-exit]')?.addEventListener('click', async (event) => {
        if (controller.state().status !== 'in_progress') return;
        event.preventDefault();
        if (controller.busy() || !window.confirm('Завершити перевірку? Зроблені відповіді залишаться в цьому браузері.')) return;
        const destination = event.currentTarget.href;
        try { if (await controller.interrupt()) window.location.assign(destination); }
        catch { showError('Не вдалося зберегти стан перевірки. Спробуйте завершити ще раз.'); }
    });
    window.addEventListener('beforeunload', (event) => {
        if (controller.state().status === 'in_progress' || controller.busy()) { event.preventDefault(); event.returnValue = ''; }
    });
    render();
    content.hidden = false;
}
