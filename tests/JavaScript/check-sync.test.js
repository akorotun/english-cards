import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createCheckState, freezeCheckState, nextCheckState } from '../../resources/js/check-session.js';
import { checkProfileLock, readCheckSessions, synchronizeCheckProfile } from '../../resources/js/check-sync.js';

const state = (uuid = 'session', userId = 1, profileId = 2) => createCheckState({
    uuid, userId, profileId, cards: [1, 2].map((id) => ({ id, word_en: `word ${id}`, word_uk: `слово ${id}` })),
}, () => 0);
const acknowledgement = (payload) => ({
    status: 'synced', session_uuid: payload.session_uuid, learning_profile_id: payload.learning_profile_id,
    applied: payload.answers.length, skipped_word_ids: [], processed_at: '2026-11-05T00:00:00Z',
});
function memoryStorage(initial) {
    const rows = new Map(initial.map((item) => [item.session_uuid, structuredClone(item)]));
    let deleteFails = false;
    return {
        rows,
        failDeletion(value) { deleteFails = value; },
        transaction() {
            const operations = [];
            const tx = { objectStore: () => ({
                getAll() { const request = {}; operations.push(() => { request.result = structuredClone([...rows.values()]); }); return request; },
                put(value) { operations.push(() => rows.set(value.session_uuid, structuredClone(value))); },
                delete(uuid) { operations.push(() => {
                    if (deleteFails) throw new Error('Disk failure during deletion');
                    rows.delete(uuid);
                }); },
            }) };
            queueMicrotask(() => {
                try { operations.forEach((run) => run()); tx.oncomplete(); }
                catch (error) { tx.error = error; tx.onabort(); }
            });
            return tx;
        },
    };
}

test('freezes a completed or abandoned session into an immutable delivery payload', () => {
    const original = nextCheckState(state(), 'know');
    const frozen = freezeCheckState(original);
    assert.equal(frozen.status, 'interrupted');
    assert.deepEqual(frozen.sync_payload.word_ids, original.first_order);
    assert.equal(frozen.sync_payload.answers.length, 1);
    assert.equal(frozen.sync_payload.status, 'interrupted');
    original.answers[0].first = 'dont_know';
    assert.equal(frozen.sync_payload.answers[0].first, 'know');
    assert.equal(freezeCheckState(frozen), frozen);
    let finished = state();
    finished = nextCheckState(nextCheckState(finished, 'know'), 'know');
    assert.equal(finished.sync_payload.status, 'completed');
    assert.equal(finished.sync_payload.answers.length, 2);
});

test('network failure leaves frozen data; retry sends the same bytes and deletes only after acknowledgement', async () => {
    const db = memoryStorage([nextCheckState(state(), 'know')]);
    const bodies = [];
    let first = true;
    let acknowledge;
    const options = { url: '/check/sync', csrf: 'token', fetch: async (url, request) => {
        assert.equal(url, '/check/sync');
        assert.equal(request.headers['X-CSRF-TOKEN'], 'token');
        assert.equal(request.credentials, 'same-origin');
        bodies.push(request.body);
        if (first) { first = false; throw new Error('offline'); }
        return new Promise((resolve) => { acknowledge = () => resolve({ ok: true, json: async () => acknowledgement(JSON.parse(request.body)) }); });
    } };
    await assert.rejects(synchronizeCheckProfile(db, 1, 2, options), /offline/);
    assert.equal(db.rows.size, 1);
    assert.equal(db.rows.get('session').status, 'interrupted');
    const pending = synchronizeCheckProfile(db, 1, 2, options);
    while (!acknowledge) await new Promise((resolve) => setImmediate(resolve));
    assert.equal(db.rows.size, 1);
    assert.equal(bodies[0], bodies[1]);
    acknowledge();
    await pending;
    assert.equal(db.rows.size, 0);
});

test('http errors, invalid json and mismatched acknowledgement retain the batch', async () => {
    const responses = [
        { ok: false, status: 409 }, { ok: false, status: 419 }, { ok: false, status: 422 }, { ok: false, status: 500 },
        { ok: true, json: async () => { throw new Error('invalid json'); } },
        { ok: true, json: async () => ({ status: 'synced', session_uuid: 'wrong', learning_profile_id: 2 }) },
        { ok: true, json: async () => ({ status: 'synced', session_uuid: 'session', learning_profile_id: 3 }) },
    ];
    for (const response of responses) {
        const db = memoryStorage([freezeCheckState(state())]);
        await assert.rejects(synchronizeCheckProfile(db, 1, 2, { fetch: async () => response }));
        assert.equal(db.rows.size, 1);
    }
});

test('a failed local deletion retries the acknowledged immutable payload safely', async () => {
    const db = memoryStorage([freezeCheckState(state())]);
    const bodies = [];
    const options = { fetch: async (url, request) => {
        bodies.push(request.body);
        return { ok: true, json: async () => acknowledgement(JSON.parse(request.body)) };
    } };
    db.failDeletion(true);
    await assert.rejects(synchronizeCheckProfile(db, 1, 2, options), /Disk/);
    assert.equal(db.rows.size, 1);
    db.failDeletion(false);
    await synchronizeCheckProfile(db, 1, 2, options);
    assert.equal(bodies[0], bodies[1]);
    assert.equal(db.rows.size, 0);
});

test('recovery isolates accounts and profiles and delivers batches sequentially in creation order', async () => {
    const first = { ...state('earlier'), created_at: 1 };
    const second = { ...state('later'), created_at: 2 };
    const foreign = state('foreign', 99, 2);
    const otherProfile = state('other-profile', 1, 7);
    const db = memoryStorage([second, foreign, otherProfile, first]);
    let inFlight = 0;
    const sent = [];
    await synchronizeCheckProfile(db, 1, 2, { fetch: async (url, request) => {
        assert.equal(inFlight++, 0);
        const payload = JSON.parse(request.body);
        sent.push(payload.session_uuid);
        assert.equal(payload.user_id, 1);
        assert.equal(payload.learning_profile_id, 2);
        await new Promise((resolve) => setImmediate(resolve));
        inFlight--;
        return { ok: true, json: async () => acknowledgement(payload) };
    } });
    assert.deepEqual(sent, ['earlier', 'later']);
    assert.deepEqual([...db.rows.keys()], ['foreign', 'other-profile']);
    assert.deepEqual((await readCheckSessions(db, 1)).map((item) => item.session_uuid), ['other-profile']);
    assert.notEqual(checkProfileLock(1, 2), checkProfileLock(99, 2));
    assert.notEqual(checkProfileLock(1, 2), checkProfileLock(1, 7));
});

test('failed earlier delivery stops the queue before later sessions', async () => {
    const db = memoryStorage([{ ...state('old'), created_at: 1 }, { ...state('new'), created_at: 2 }]);
    const sent = [];
    await assert.rejects(synchronizeCheckProfile(db, 1, 2, { fetch: async (url, request) => {
        sent.push(JSON.parse(request.body).session_uuid);
        return { ok: false, status: 500 };
    } }));
    assert.deepEqual(sent, ['old']);
    assert.equal(db.rows.size, 2);
    assert.equal(db.rows.get('new').sync_payload, undefined);
});

async function withSyncScreen(db, fetch, run, locks = { request: async (name, options, callback) => callback({ name }) }) {
    const { initializeCheckSync } = await import('../../resources/js/check-sync.js');
    const oldWindow = globalThis.window;
    const oldFetch = globalThis.fetch;
    const element = () => ({ hidden: true, textContent: '', disabled: false, handlers: {}, addEventListener(event, callback) { this.handlers[event] = callback; } });
    const message = element();
    const retry = element();
    const root = {
        hidden: true, dataset: { userId: '1', url: '/check/sync', csrf: 'csrf' },
        querySelector: (selector) => selector === '[data-check-sync-message]' ? message : retry,
    };
    globalThis.window = { navigator: { locks }, addEventListener() {}, indexedDB: { open() {
        const request = { result: db };
        queueMicrotask(() => request.onsuccess());
        return request;
    } } };
    globalThis.fetch = fetch;
    try { await run({ root, message, retry, initialize: (sessionRoot) => initializeCheckSync(root, sessionRoot) }); }
    finally { globalThis.window = oldWindow; globalThis.fetch = oldFetch; }
}

test('application recovery skips another live tab and never sends another account data', async () => {
    const db = memoryStorage([state('live'), state('foreign', 99, 2), state('abandoned', 1, 7)]);
    const sent = [];
    await withSyncScreen(db, async (url, request) => {
        const payload = JSON.parse(request.body); sent.push(payload);
        return { ok: true, json: async () => acknowledgement(payload) };
    }, async ({ initialize }) => {
        await initialize();
        assert.deepEqual(sent.map((payload) => payload.session_uuid), ['abandoned']);
        assert.deepEqual([...db.rows.keys()], ['live', 'foreign']);
        assert.equal(db.rows.get('live').status, 'in_progress');
    }, { request: async (name, options, callback) => callback(name === checkProfileLock(1, 2) ? null : { name }) });
});

test('a failed older batch blocks new cards and keeps the retry action available', async () => {
    const db = memoryStorage([nextCheckState(state(), 'know')]);
    await withSyncScreen(db, async () => ({ ok: false, status: 503 }), async ({ initialize, root, message, retry }) => {
        await initialize({ dataset: { profileId: '2' }, querySelector() { assert.fail('New card screen must not start before pending delivery succeeds'); } });
        assert.equal(root.hidden, false);
        assert.equal(retry.hidden, false);
        assert.equal(retry.disabled, false);
        assert.match(message.textContent, /Дані залишаються/);
        assert.equal(db.rows.get('session').status, 'interrupted');
    });
});

test('background retry preserves errors of another profile and removes only confirmed batches', async () => {
    const db = memoryStorage([state('pending'), state('other', 1, 7)]);
    let offline = true;
    await withSyncScreen(db, async (url, request) => {
        const payload = JSON.parse(request.body);
        if (offline && payload.learning_profile_id === 2) throw new Error('offline');
        return { ok: true, json: async () => acknowledgement(payload) };
    }, async ({ initialize, message, retry }) => {
        await initialize();
        assert.deepEqual([...db.rows.keys()], ['pending']);
        assert.equal(retry.hidden, false);
        assert.match(message.textContent, /Не вдалося синхронізувати/);
        offline = false;
        await retry.handlers.click();
        assert.equal(db.rows.size, 0);
        assert.equal(retry.hidden, true);
        assert.match(message.textContent, /Прогрес синхронізовано/);
    });
});

test('unavailable cross-tab protection blocks new cards without deleting saved responses', async () => {
    const db = memoryStorage([state()]);
    await withSyncScreen(db, async () => assert.fail('Must not deliver without a profile lock'), async ({ initialize, message, retry }) => {
        await initialize({ dataset: { profileId: '2' } });
        assert.match(message.textContent, /HTTPS або localhost/);
        assert.equal(retry.hidden, false);
        assert.equal(db.rows.size, 1);
    }, null);
});
