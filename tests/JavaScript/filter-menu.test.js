import { test } from 'node:test';
import assert from 'node:assert/strict';
import { closeFilterAfterFocusChange } from '../../resources/js/filter-menu.js';

test('closes only after focus actually leaves the open menu', async () => {
    const inside = {};
    const outside = {};
    const currentDocument = { activeElement: inside };
    const menu = { open: true, contains: (element) => element === inside };

    closeFilterAfterFocusChange(menu, currentDocument);
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(menu.open, true);

    currentDocument.activeElement = outside;
    closeFilterAfterFocusChange(menu, currentDocument);
    assert.equal(menu.open, true);
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(menu.open, false);
});

test('does not close a menu when focus returns before the deferred check', async () => {
    const inside = {};
    const currentDocument = { activeElement: null };
    const menu = { open: true, contains: (element) => element === inside };

    closeFilterAfterFocusChange(menu, currentDocument);
    currentDocument.activeElement = inside;
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(menu.open, true);
});
