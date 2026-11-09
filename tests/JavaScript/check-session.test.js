import { test } from 'node:test';
import assert from 'node:assert/strict';
import { checkResults, createCheckState, nextCheckState, createCheckController, initializeCheckSession, openCheckStorage, writeCheckState } from '../../resources/js/check-session.js';

const cards = [1, 2, 3].map((id) => ({ id, word_en: `word ${id}`, word_uk: `слово ${id}` }));
const makeState = (items = cards) => createCheckState({ uuid: 'uuid', userId: 1, profileId: 2, cards: items }, () => 0);

test('rejects empty and duplicate cards and shuffles first round once', () => {
    assert.throws(() => makeState([]));
    assert.throws(() => makeState([cards[0], cards[0]]));
    const state = makeState();
    assert.deepEqual(state.first_order, [2, 3, 1]);
    assert.deepEqual(state.word_ids, [1, 2, 3]);
    assert.equal(state.round, 1);
});

test('all known ends after one round; a single unknown gets exactly one repeat', () => {
    let state = makeState([cards[0]]);
    state = nextCheckState(state, 'know');
    assert.equal(state.status, 'ready_to_sync');
    assert.throws(() => nextCheckState(state, 'know'));
    state = nextCheckState(makeState([cards[0]]), 'dont_know');
    assert.deepEqual(state.repeat_order, [1]);
    assert.equal(state.round, 2);
    state = nextCheckState(state, 'know');
    assert.equal(state.status, 'ready_to_sync');
    assert.deepEqual(state.answers, [{ word_id: 1, first: 'dont_know', repeat: 'know' }]);
});

test('repeat round shuffles only unknown words after all initial answers', () => {
    let state = makeState();
    state = nextCheckState(state, 'dont_know', () => 0);
    state = nextCheckState(state, 'know', () => 0);
    assert.equal(state.round, 1);
    state = nextCheckState(state, 'dont_know', () => 0);
    assert.deepEqual(state.repeat_order, [1, 2]);
    assert.equal(state.round, 2);
    state = nextCheckState(state, 'dont_know');
    state = nextCheckState(state, 'know');
    assert.equal(state.status, 'ready_to_sync');
    assert.deepEqual(state.answers, [
        { word_id: 2, first: 'dont_know', repeat: 'know' },
        { word_id: 3, first: 'know' },
        { word_id: 1, first: 'dont_know', repeat: 'dont_know' },
    ]);
});

test('results count session answers and repeat only words missed in the second round', () => {
    const finish = (answers) => answers.reduce((state, answer) => nextCheckState(state, answer, () => 0), makeState());
    assert.deepEqual(checkResults(finish(['know', 'know', 'know']).answers), {
        checked: 3, firstKnown: 3, neededRepeat: 0, remembered: 0, stillUnknown: 0, unknownIds: new Set(),
    });
    assert.deepEqual(checkResults(finish(['dont_know', 'know', 'dont_know', 'dont_know', 'know']).answers), {
        checked: 3, firstKnown: 1, neededRepeat: 2, remembered: 1, stillUnknown: 1, unknownIds: new Set([1]),
    });
    assert.deepEqual(checkResults(finish(Array(6).fill('dont_know')).answers), {
        checked: 3, firstKnown: 0, neededRepeat: 3, remembered: 0, stillUnknown: 3, unknownIds: new Set([1, 2, 3]),
    });
    assert.equal(checkResults(finish(['dont_know']).answers).stillUnknown, 0);
});

test('answers work without revealing; pending write blocks duplicate and failed write preserves state', async () => {
    let finish;
    const writes = [];
    let fail = false;
    const controller = createCheckController(makeState([cards[0]]), (state) => {
        writes.push(state);
        if (fail) return Promise.reject(new Error('disk'));
        return new Promise((resolve) => { finish = resolve; });
    });
    assert.equal(controller.revealed(), false);
    const pending = controller.answer('dont_know');
    assert.equal(await controller.answer('know'), false);
    assert.equal(controller.state().answers.length, 0);
    finish();
    assert.equal(await pending, true);
    assert.equal(controller.revealed(), false);
    assert.equal(controller.state().round, 2);
    controller.toggleTranslation();
    fail = true;
    await assert.rejects(controller.answer('know'));
    assert.equal(controller.revealed(), true);
    assert.equal(controller.state().answers[0].repeat, undefined);
    fail = false;
    const retry = controller.answer('know');
    finish();
    assert.equal(await retry, true);
    assert.equal(controller.state().status, 'ready_to_sync');
    assert.equal(writes.length, 3);
});

test('interruption persists only given answers and a failed save leaves session in progress', async () => {
    const state = nextCheckState(makeState(), 'know');
    const controller = createCheckController(state, async () => { throw new Error('failed'); });
    await assert.rejects(controller.interrupt());
    assert.equal(controller.state().status, 'in_progress');
    let saved;
    const success = createCheckController(state, async (value) => { saved = value; });
    assert.equal(await success.interrupt(), true);
    assert.equal(saved.status, 'interrupted');
    assert.equal(saved.answers.length, 1);
    assert.equal(await success.answer('know'), false);
});

test('storage failures are propagated and success waits for transaction completion', async () => {
    await assert.rejects(openCheckStorage(undefined));
    let tx;
    const db = { transaction: () => (tx = { objectStore: () => ({ add: () => {}, put: () => {} }) }) };
    let settled = false;
    const write = writeCheckState(db, makeState(), true).then(() => { settled = true; });
    await Promise.resolve();
    assert.equal(settled, false);
    tx.oncomplete();
    await write;
    assert.equal(settled, true);
    const failed = writeCheckState(db, makeState());
    tx.error = new Error('quota');
    tx.onabort();
    await assert.rejects(failed, /quota/);
});

function checkScreen() {
    const element = () => ({
        hidden: false, textContent: '', disabled: false, dataset: {}, handlers: {}, attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(type, handler) { this.handlers[type] = handler; },
        focus() {},
        showModal() { this.open = true; },
        close() { this.open = false; },
        click() { return this.handlers.click?.({ currentTarget: this }); },
    });
    const selectors = [
        'error', 'content', 'card-screen', 'complete', 'reveal', 'answers', 'audio-feedback',
        'round', 'progress', 'counter', 'en', 'uk', 'speak', 'exit', 'dialog', 'dialog-error',
        'continue', 'confirm', 'complete-count', 'complete-title', 'complete-sync', 'complete-retry', 'restart',
    ];
    const elements = Object.fromEntries(selectors.map((name) => [`[data-check-${name}]`, element()]));
    const unknown = element(); unknown.dataset.checkAnswer = 'dont_know';
    const known = element(); known.dataset.checkAnswer = 'know';
    const buttons = [unknown, known];
    elements['[data-check-answers]'].querySelectorAll = () => buttons;
    elements['[data-check-complete]'].querySelectorAll = () => [];
    const root = {
        dataset: { userId: '1', profileId: '2', cards: JSON.stringify(cards.slice(0, 2)), restartUrl: '/check/start' },
        querySelector: (selector) => elements[selector],
    };
    const saved = [];
    let pendingTransaction;
    const db = {
        createObjectStore: () => ({ createIndex() {} }),
        transaction() {
            const tx = {
                objectStore: () => ({ add: (value) => { tx.value = structuredClone(value); }, put: (value) => { tx.value = structuredClone(value); } }),
                complete() { saved.push(tx.value); tx.oncomplete(); },
                abort() { tx.error = new Error('quota'); tx.onabort(); },
            };
            if (!saved.length) queueMicrotask(() => tx.complete());
            else pendingTransaction = tx;
            return tx;
        },
    };
    const indexedDB = { open() {
        const request = { result: db };
        queueMicrotask(() => { request.onupgradeneeded(); request.onsuccess(); });
        return request;
    } };
    const oldWindow = globalThis.window;
    const oldDocument = globalThis.document;
    let uuid = 0;
    globalThis.window = { indexedDB, crypto: { randomUUID: () => `test-${++uuid}` }, addEventListener() {} };
    globalThis.document = { querySelector: () => null, addEventListener() {} };
    return {
        root, elements, db, saved, known, unknown,
        complete: () => pendingTransaction.complete(),
        abort: () => pendingTransaction.abort(),
        restore() {
            globalThis.window = oldWindow;
            globalThis.document = oldDocument;
        },
    };
}

test('screen reveals the translation and advances only after a successful IndexedDB transaction', async () => {
    const screen = checkScreen();
    const { root, elements, saved, known, unknown } = screen;
    try {
        await initializeCheckSession(root);
        assert.equal(saved.length, 1);
        assert.equal(saved[0].user_id, 1);
        assert.equal(saved[0].learning_profile_id, 2);
        assert.equal(elements['[data-check-uk]'].attributes['aria-hidden'], 'true');
        assert.ok(elements['[data-check-uk]'].textContent);
        assert.equal(elements['[data-check-reveal]'].disabled, false);
        assert.equal(elements['[data-check-answers]'].hidden, false);
        const firstWord = elements['[data-check-en]'].textContent;
        const knownWithoutReveal = known.click();
        assert.equal(elements['[data-check-en]'].textContent, firstWord);
        screen.complete();
        await knownWithoutReveal;
        assert.notEqual(elements['[data-check-en]'].textContent, firstWord);
        assert.equal(saved[1].answers[0].first, 'know');
        assert.equal(elements['[data-check-answers]'].hidden, false);
        elements['[data-check-speak]'].click();
        assert.equal(saved.length, 2);
        elements['[data-check-reveal]'].click();
        assert.equal(elements['[data-check-reveal]'].attributes['aria-expanded'], 'true');
        assert.equal(elements['[data-check-reveal]'].disabled, false);
        assert.equal(elements['[data-check-uk]'].attributes['aria-hidden'], 'false');
        assert.ok(elements['[data-check-uk]'].textContent);
        elements['[data-check-reveal]'].click();
        assert.equal(elements['[data-check-reveal]'].attributes['aria-expanded'], 'false');
        assert.equal(elements['[data-check-uk]'].attributes['aria-hidden'], 'true');
        elements['[data-check-reveal]'].click();
        assert.equal(elements['[data-check-uk]'].attributes['aria-hidden'], 'false');
        assert.equal(saved.length, 2);
        assert.equal(elements['[data-check-answers]'].hidden, false);
        const previousWord = elements['[data-check-en]'].textContent;
        const failed = unknown.click();
        assert.equal(unknown.disabled, true);
        assert.equal(elements['[data-check-en]'].textContent, previousWord);
        screen.abort();
        await failed;
        assert.equal(elements['[data-check-en]'].textContent, previousWord);
        assert.equal(elements['[data-check-uk]'].attributes['aria-hidden'], 'false');
        assert.equal(elements['[data-check-error]'].hidden, false);
        assert.equal(saved.length, 2);
        const retry = unknown.click();
        screen.complete();
        await retry;
        assert.equal(saved.length, 3);
        assert.equal(saved[2].answers.length, 2);
        assert.equal(saved[2].answers[1].first, 'dont_know');
        assert.equal(elements['[data-check-round]'].textContent, 'Коло 2 — повторення');
        assert.equal(elements['[data-check-uk]'].attributes['aria-hidden'], 'true');
        assert.equal(elements['[data-check-reveal]'].attributes['aria-expanded'], 'false');
        assert.equal(elements['[data-check-reveal]'].disabled, false);
        assert.equal(elements['[data-check-answers]'].hidden, false);
        elements['[data-check-exit]'].click();
        assert.equal(elements['[data-check-dialog]'].open, true);
        elements['[data-check-continue]'].click();
        assert.equal(elements['[data-check-dialog]'].open, false);
        assert.equal(saved.length, 3);
        elements['[data-check-exit]'].click();
        const failedStop = elements['[data-check-confirm]'].click();
        assert.equal(elements['[data-check-continue]'].disabled, true);
        screen.abort();
        await failedStop;
        assert.equal(elements['[data-check-dialog]'].open, true);
        assert.equal(elements['[data-check-dialog-error]'].hidden, false);
        assert.equal(elements['[data-check-card-screen]'].hidden, false);
        const stop = elements['[data-check-confirm]'].click();
        screen.complete();
        await stop;
        assert.equal(saved[3].status, 'interrupted');
        assert.equal(saved[3].answers.length, 2);
        assert.equal(elements['[data-check-dialog]'].open, false);
        assert.equal(elements['[data-check-complete]'].hidden, false);
        assert.equal(elements['[data-check-complete-count]'].textContent, 2);
        assert.equal(elements['[data-check-complete-title]'].textContent, 'Перевірку перервано');
        assert.equal(elements['[data-check-restart]'].hidden, true);
        assert.equal(elements['[data-check-card-screen]'].hidden, true);
    } finally {
        screen.restore();
    }
});

test('repeat waits for confirmation and uses a fresh UUID for only the missed cards', async () => {
    const screen = checkScreen();
    const { root, elements, saved, known, unknown, db } = screen;
    const restart = elements['[data-check-restart]'];
    const retry = elements['[data-check-complete-retry]'];
    const syncRequests = [];
    let confirmSync;
    let fetched = 0;
    let profileId = 3;
    window.fetch = async () => {
        fetched++;
        return { ok: true, json: async () => ({ user_id: 1, learning_profile_id: profileId, cards }) };
    };
    try {
        await initializeCheckSession(root, {
            db,
            sync(uuid) {
                syncRequests.push(uuid);
                return new Promise((resolve) => { confirmSync = resolve; });
            },
        });
        const first = known.click();
        screen.complete();
        await first;
        const second = unknown.click();
        screen.complete();
        await second;
        const last = unknown.click();
        screen.complete();
        await new Promise(setImmediate);
        assert.equal(restart.hidden, false);
        assert.equal(restart.disabled, true);
        await restart.click();
        assert.equal(fetched, 0);
        confirmSync({ synced: false, message: 'Offline' });
        await last;
        assert.equal(retry.hidden, false);
        assert.equal(restart.disabled, true);
        const pending = retry.click();
        confirmSync({ synced: true, message: 'Synced' });
        await pending;
        assert.deepEqual(syncRequests, [saved[0].session_uuid, saved[0].session_uuid]);
        assert.equal(restart.disabled, false);
        await restart.click();
        assert.equal(elements['[data-check-error]'].hidden, false);
        assert.equal(saved.length, 4);
        profileId = 2;
        const restarted = restart.click();
        await new Promise(setImmediate);
        screen.complete();
        await restarted;
        assert.notEqual(saved[4].session_uuid, saved[0].session_uuid);
        assert.deepEqual(saved[4].word_ids, [saved[3].answers.find((answer) => answer.repeat === 'dont_know').word_id]);
        assert.equal(saved[4].round, 1);
        assert.equal(elements['[data-check-complete]'].hidden, true);
    } finally {
        screen.restore();
    }
});
