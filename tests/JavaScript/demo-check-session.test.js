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

test('guest check reveals each translation before advancing and restarts without ratings', () => {
    const screen = makeScreen([{ id: 1, word_en: 'one', word_uk: 'один' }, { id: 2, word_en: 'two', word_uk: 'два' }]);
    initializeDemoCheckSession(screen.root);
    const get = screen.get;
    const first = get('check-en').textContent;
    assert.equal(get('demo-check-counter').textContent, '1 / 2');
    assert.equal(get('check-uk').attributes['aria-hidden'], 'true');
    assert.equal(get('demo-check-next').disabled, true);
    get('demo-check-next').click();
    assert.equal(get('check-en').textContent, first);
    get('check-reveal').click();
    assert.equal(get('check-uk').attributes['aria-hidden'], 'false');
    assert.equal(get('check-reveal').attributes['aria-expanded'], 'true');
    assert.equal(get('check-uk').textContent, first === 'one' ? 'один' : 'два');
    assert.equal(get('demo-check-next').disabled, false);
    get('demo-check-next').click();
    assert.notEqual(get('check-en').textContent, first);
    assert.equal(get('demo-check-counter').textContent, '2 / 2');
    assert.equal(get('check-uk').attributes['aria-hidden'], 'true');
    assert.equal(get('demo-check-next').disabled, true);
    get('check-reveal').click();
    get('demo-check-next').click();
    assert.equal(get('demo-check-complete').hidden, false);
    get('demo-check-restart').click();
    assert.equal(get('demo-check-complete').hidden, true);
    assert.equal(get('demo-check-counter').textContent, '1 / 2');
    assert.equal(get('check-uk').attributes['aria-hidden'], 'true');
    assert.equal(get('demo-check-next').disabled, true);
});

test('one card can be completed and hiding the translation disables next again', () => {
    const screen = makeScreen([{ id: 1, word_en: 'one', word_uk: 'один' }]);
    initializeDemoCheckSession(screen.root);
    const get = screen.get;
    get('check-reveal').click();
    get('check-reveal').click();
    assert.equal(get('demo-check-next').disabled, true);
    get('check-reveal').click();
    get('demo-check-next').click();
    assert.equal(get('demo-check-complete').hidden, false);
});
