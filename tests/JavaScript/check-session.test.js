import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createCheckState, nextCheckState, createCheckController, openCheckStorage, writeCheckState } from '../../resources/js/check-session.js';

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

test('reveal gates answers; pending write blocks duplicate; failed write preserves state for retry', async () => {
    let finish;
    const writes = [];
    let fail = false;
    const controller = createCheckController(makeState([cards[0]]), (state) => {
        writes.push(state);
        if (fail) return Promise.reject(new Error('disk'));
        return new Promise((resolve) => { finish = resolve; });
    });
    assert.equal(await controller.answer('know'), false);
    assert.equal(controller.revealed(), false);
    controller.reveal();
    const pending = controller.answer('dont_know');
    assert.equal(await controller.answer('know'), false);
    assert.equal(controller.state().answers.length, 0);
    finish();
    assert.equal(await pending, true);
    assert.equal(controller.revealed(), false);
    assert.equal(controller.state().round, 2);
    controller.reveal();
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
