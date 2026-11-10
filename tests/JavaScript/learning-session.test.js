import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createLearningSession, initializeLearningSession } from '../../resources/js/learning-session.js';

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

test('learning screen renders both languages and supports buttons, keyboard, swipes and restart', () => {
    const element = () => ({
        hidden: false, disabled: false, textContent: '', handlers: {},
        addEventListener(type, handler) { this.handlers[type] = handler; },
        click() { if (!this.disabled) this.handlers.click?.(); },
        focus() {},
    });
    const names = ['card-screen', 'complete', 'previous', 'next', 'card', 'audio-feedback', 'en', 'uk', 'counter', 'progress', 'speak', 'restart', 'content'];
    const elements = Object.fromEntries(names.map((name) => [`[data-learning-${name}]`, element()]));
    const root = {
        dataset: { cards: JSON.stringify([{ word_en: 'one', word_uk: 'один' }, { word_en: 'two', word_uk: 'два' }]) },
        querySelector: (selector) => elements[selector],
    };
    const oldDocument = globalThis.document;
    const documentHandlers = {};
    globalThis.document = { addEventListener: (type, handler) => { documentHandlers[type] = handler; } };
    try {
        initializeLearningSession(root);
        const get = (name) => elements[`[data-learning-${name}]`];
        const first = get('en').textContent;
        assert.equal(get('uk').textContent, first === 'one' ? 'один' : 'два');
        assert.equal(get('counter').textContent, '1 / 2');
        assert.equal(get('previous').disabled, true);
        get('next').click();
        assert.equal(get('counter').textContent, '2 / 2');
        assert.notEqual(get('en').textContent, first);
        get('previous').click();
        assert.equal(get('en').textContent, first);
        documentHandlers.keydown({ key: 'ArrowRight', target: { closest: () => false }, preventDefault() {} });
        assert.equal(get('counter').textContent, '2 / 2');
        get('card').handlers.touchstart({ touches: [{ clientX: 0, clientY: 0 }] });
        get('card').handlers.touchend({ changedTouches: [{ clientX: 80, clientY: 5 }] });
        assert.equal(get('counter').textContent, '1 / 2');
        get('card').handlers.touchstart({ touches: [{ clientX: 80, clientY: 0 }] });
        get('card').handlers.touchend({ changedTouches: [{ clientX: 0, clientY: 5 }] });
        get('next').click();
        assert.equal(get('complete').hidden, false);
        get('restart').click();
        assert.equal(get('complete').hidden, true);
        assert.equal(get('counter').textContent, '1 / 2');
    } finally {
        globalThis.document = oldDocument;
    }
});

import { speakEnglish } from '../../resources/js/learning-session.js';

test('speech starts only when requested, with an English voice when available', () => {
    const spoken = [];
    let canceled = 0;
    const english = { lang: 'en-GB' };
    const browser = {
        SpeechSynthesisUtterance: class { constructor(text) { this.text = text; } },
        speechSynthesis: {
            getVoices: () => [{ lang: 'uk-UA' }, english],
            cancel: () => canceled++,
            speak: (utterance) => spoken.push(utterance),
        },
    };
    let errors = 0;
    assert.equal(spoken.length, 0);
    speakEnglish('hello', browser, () => errors++);
    assert.equal(spoken[0].text, 'hello');
    assert.equal(spoken[0].lang, 'en-US');
    assert.equal(spoken[0].voice, english);
    assert.equal(canceled, 1);
    spoken[0].onerror({ error: 'interrupted' });
    assert.equal(errors, 0);
    spoken[0].onerror({ error: 'audio-busy' });
    assert.equal(errors, 1);
});

test('missing speech support and playback exceptions are reported without breaking the session', () => {
    let errors = 0;
    speakEnglish('hello', {}, () => errors++);
    const browser = {
        SpeechSynthesisUtterance: class {},
        speechSynthesis: { getVoices: () => [], cancel: () => {}, speak: () => { throw new Error('failed'); } },
    };
    speakEnglish('hello', browser, () => errors++);
    assert.equal(errors, 2);
    assert.equal(createLearningSession(['hello']).current(), 'hello');
});
