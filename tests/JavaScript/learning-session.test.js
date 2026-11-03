import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createLearningSession } from '../../resources/js/learning-session.js';

test('navigation preserves shuffled order, respects boundaries, completes and restarts', () => {
    let random = 0;
    const session = createLearningSession(['a', 'b', 'c'], () => random);
    assert.equal(session.current(), 'b');
    session.previous();
    assert.equal(session.position(), 0);
    session.next();
    assert.equal(session.current(), 'c');
    session.previous();
    assert.equal(session.current(), 'b');
    session.next();
    session.next();
    assert.equal(session.current(), 'a');
    session.next();
    assert.equal(session.completed(), true);
    session.next();
    assert.equal(session.position(), 3);
    session.previous();
    assert.equal(session.current(), 'a');
    random = 0.99;
    session.restart();
    assert.equal(session.completed(), false);
    assert.equal(session.position(), 0);
    assert.equal(session.current(), 'a');
});
