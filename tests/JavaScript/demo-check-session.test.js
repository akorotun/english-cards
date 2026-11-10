import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initializeDemoCheckSession } from '../../resources/js/demo-check-session.js';

function makeScreen(cards) {
    const element = () => ({
        hidden: false, disabled: false, textContent: '', attributes: {}, handlers: {},
        addEventListener(type, handler) { this.handlers[type] = handler; },
        setAttribute(name, value) { this.attributes[name] = value; },
        click() { if (!this.disabled) this.handlers.click?.(); },
        focus() {},
    });
    const names = ['demo-check-card-screen', 'demo-check-complete', 'demo-check-next', 'check-reveal', 'check-uk', 'check-audio-feedback', 'check-en', 'demo-check-counter', 'demo-check-progress', 'demo-check-restart', 'check-speak', 'demo-check-content'];
    const elements = Object.fromEntries(names.map((name) => [`[data-${name}]`, element()]));
    return {
        root: { dataset: { cards: JSON.stringify(cards) }, querySelector: (selector) => elements[selector] },
        get: (name) => elements[`[data-${name}]`],
    };
}

test('guest check can advance without revealing and resets translation for each card and restart', () => {
    const screen = makeScreen([{ id: 1, word_en: 'one', word_uk: 'один' }, { id: 2, word_en: 'two', word_uk: 'два' }]);
    initializeDemoCheckSession(screen.root);
    const get = screen.get;
    const first = get('check-en').textContent;
    assert.equal(get('demo-check-counter').textContent, '1 / 2');
    assert.equal(get('check-uk').attributes['aria-hidden'], 'true');
    assert.equal(get('demo-check-next').disabled, false);
    get('demo-check-next').click();
    assert.notEqual(get('check-en').textContent, first);
    assert.equal(get('demo-check-counter').textContent, '2 / 2');
    assert.equal(get('check-uk').attributes['aria-hidden'], 'true');
    get('check-reveal').click();
    assert.equal(get('check-uk').attributes['aria-hidden'], 'false');
    assert.equal(get('check-reveal').attributes['aria-expanded'], 'true');
    assert.equal(get('check-uk').textContent, first === 'one' ? 'два' : 'один');
    assert.equal(get('demo-check-next').disabled, false);
    get('check-reveal').click();
    assert.equal(get('check-uk').attributes['aria-hidden'], 'true');
    assert.equal(get('demo-check-next').disabled, false);
    get('demo-check-next').click();
    assert.equal(get('demo-check-complete').hidden, false);
    get('demo-check-restart').click();
    assert.equal(get('demo-check-complete').hidden, true);
    assert.equal(get('demo-check-counter').textContent, '1 / 2');
    assert.equal(get('check-uk').attributes['aria-hidden'], 'true');
    assert.equal(get('demo-check-next').disabled, false);
});

test('one card can be completed without opening its translation', () => {
    const screen = makeScreen([{ id: 1, word_en: 'one', word_uk: 'один' }]);
    initializeDemoCheckSession(screen.root);
    const get = screen.get;
    assert.equal(get('check-uk').attributes['aria-hidden'], 'true');
    get('demo-check-next').click();
    assert.equal(get('demo-check-complete').hidden, false);
});

test('guest check keeps pronunciation available without revealing the translation', () => {
    const screen = makeScreen([{ id: 1, word_en: 'hello', word_uk: 'привіт' }]);
    const oldWindow = globalThis.window;
    const spoken = [];
    globalThis.window = {
        SpeechSynthesisUtterance: class { constructor(text) { this.text = text; } },
        speechSynthesis: { getVoices: () => [], cancel() {}, speak: (utterance) => spoken.push(utterance.text) },
    };
    try {
        initializeDemoCheckSession(screen.root);
        screen.get('check-speak').click();
        assert.deepEqual(spoken, ['hello']);
        assert.equal(screen.get('check-uk').attributes['aria-hidden'], 'true');
        assert.equal(screen.get('demo-check-next').disabled, false);
    } finally {
        globalThis.window = oldWindow;
    }
});
