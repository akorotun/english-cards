document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    button.addEventListener('click', () => {
        const isVisible = input.type === 'password';
        const action = isVisible ? 'Приховати' : 'Показати';

        input.type = isVisible ? 'text' : 'password';
        button.textContent = action;
        button.setAttribute('aria-label', `${action} ${button.dataset.passwordLabel}`);
        button.setAttribute('aria-pressed', String(isVisible));
    });
});

const systemDark = window.matchMedia('(prefers-color-scheme: dark)');
const themeColor = document.querySelector('meta[name="theme-color"]');
const feedback = document.querySelector('[data-ui-feedback]');

function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;

    const selectedOption = document.querySelector(`[data-theme-option="${theme}"]`);
    document.querySelectorAll('[data-theme-current-label]').forEach((label) => {
        label.textContent = selectedOption?.textContent.trim() || label.textContent;
    });

    if (themeColor) {
        themeColor.content = theme === 'dark' || (theme === 'system' && systemDark.matches) ? '#0a1425' : '#edf6ff';
    }

    document.querySelectorAll('[data-theme-option]').forEach((option) => {
        option.setAttribute('aria-pressed', String(option.dataset.themeOption === theme));
    });
}

function showFeedback(message) {
    if (feedback) {
        feedback.textContent = message;
        feedback.hidden = false;
    }
}

async function submitJson(form) {
    const response = await fetch(form.action, {
        method: form.method,
        body: new FormData(form),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }

    return response.json();
}

applyTheme(document.documentElement.dataset.theme || 'system');
systemDark.addEventListener('change', () => {
    if (document.documentElement.dataset.theme === 'system') {
        applyTheme('system');
    }
});

document.querySelectorAll('[data-theme-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        try {
            const result = await submitJson(form);
            applyTheme(result.theme_preference);
            form.closest('[data-theme-menu]').open = false;
        } catch {
            showFeedback('Не вдалося змінити тему. Спробуйте ще раз.');
        }
    });
});

document.querySelectorAll('[data-profile-activate]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        try {
            const result = await submitJson(form);
            applyTheme(result.theme_preference);
            const profileMenu = document.querySelector('[data-header-menu].profile-menu');

            if (profileMenu) {
                profileMenu.hidden = false;
                profileMenu.querySelector('[data-active-profile-initial]').textContent = Array.from(result.profile_name)[0]?.toLocaleUpperCase('uk') || '';
                profileMenu.querySelectorAll('[data-active-profile-name]').forEach((name) => {
                    name.textContent = result.profile_name;
                });
                const profileTrigger = profileMenu.querySelector('[data-active-profile-trigger]');
                profileTrigger.setAttribute('aria-label', `Активний профіль: ${result.profile_name}`);
                profileTrigger.title = `Активний профіль: ${result.profile_name}`;
            }
            document.querySelectorAll('[data-profile-item]').forEach((item) => {
                item.querySelector('[data-profile-current]').hidden = item.dataset.profileItem !== String(result.profile_id);
            });
            document.querySelectorAll('[data-theme-form]').forEach((themeForm) => {
                themeForm.action = result.theme_url;
            });
            if (!document.querySelector('[data-theme-menu]')) {
                window.location.reload();
                return;
            }
            showFeedback('Активний профіль змінено.');
        } catch {
            showFeedback('Не вдалося обрати профіль. Спробуйте ще раз.');
        }
    });
});

document.addEventListener('click', (event) => {
    document.querySelectorAll('[data-dropdown][open]').forEach((menu) => {
        if (!menu.contains(event.target)) {
            menu.open = false;
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('[data-dropdown][open]').forEach((menu) => {
            menu.open = false;
            menu.querySelector('summary').focus();
        });
    }
});

document.querySelectorAll('[data-dropdown]').forEach((menu) => {
    menu.addEventListener('toggle', () => {
        if (menu.open) {
            document.querySelectorAll('[data-dropdown][open]').forEach((otherMenu) => {
                if (otherMenu !== menu) {
                    otherMenu.open = false;
                }
            });
        }
    });
});

import { closeFilterAfterFocusChange } from './filter-menu';

document.querySelectorAll('[data-filter-menu]').forEach((menu) => {
    const trigger = menu.querySelector('summary');

    menu.addEventListener('focusout', () => {
        closeFilterAfterFocusChange(menu);
    });

    menu.addEventListener('change', (event) => {
        const input = event.target;

        if (input instanceof HTMLInputElement && input.checked) {
            menu.querySelector('[data-filter-value]').textContent = input.closest('label').querySelector('span').textContent;
        }
    });

    menu.addEventListener('click', (event) => {
        if (event.target instanceof HTMLInputElement && event.detail > 0) {
            menu.open = false;
            trigger.focus();
        }
    });

    menu.addEventListener('keydown', (event) => {
        if (event.target === trigger && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            menu.open = true;
            menu.querySelector('input:checked')?.focus();
        } else if (event.target instanceof HTMLInputElement && event.key === 'Enter') {
            event.preventDefault();
            menu.open = false;
            trigger.focus();
        }
    });
});

document.querySelectorAll('[data-import-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');

        if (button instanceof HTMLButtonElement) {
            button.disabled = true;
            button.textContent = form.dataset.loadingLabel || 'Зачекайте…';
        }
    });
});

import { initializeLearningSession } from './learning-session';

document.querySelectorAll('[data-learning-session]').forEach(initializeLearningSession);


document.querySelectorAll('[data-learning-selection]').forEach((form) => {
    const filter = form.querySelector('[data-learned-filter]');
    const checkbox = filter.querySelector('input[type="checkbox"]');
    const topics = [...form.querySelectorAll('input[name="topic_ids[]"]')];

    function updateLearnedFilter() {
        const available = topics.some((topic) => topic.checked && topic.dataset.hasLearnedWords === '1');
        filter.hidden = !available;
        if (!available) checkbox.checked = false;
    }

    topics.forEach((topic) => topic.addEventListener('change', updateLearnedFilter));
    updateLearnedFilter();
});


document.querySelectorAll('[data-learning-selection]').forEach((form) => {
    const result = document.querySelector('[data-selection-result]');
    const filters = form.querySelector('[data-topic-filters]');
    const search = form.querySelector('[data-topic-search]');
    const clearSearch = form.querySelector('[data-clear-search]');
    const scope = form.querySelector('[data-topic-scope]');
    const level = form.querySelector('[data-topic-level]');
    const rows = [...form.querySelectorAll('[data-topic-row]')];
    filters.hidden = false;

    if (result && form.dataset.hasResult === '1') {
        form.hidden = true;
        result.querySelector('a[href="#topic-selection"]').addEventListener('click', (event) => {
            event.preventDefault();
            result.hidden = true;
            form.hidden = false;
            form.querySelector('#topic-selection').scrollIntoView();
        });
    }

    function filterTopics() {
        clearSearch.hidden = search.value.length === 0;
        let visible = 0;
        rows.forEach((row) => {
            const matches = row.dataset.name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())
                && (!scope.querySelector('input:checked').value || row.dataset.scope === scope.querySelector('input:checked').value)
                && (!level.querySelector('input:checked').value || row.dataset.level === level.querySelector('input:checked').value);
            row.hidden = !matches;
            if (matches) visible++;
        });
        form.querySelector('[data-filter-empty]').hidden = visible !== 0;
    }

    [search, scope, level].forEach((input) => input.addEventListener('input', filterTopics));
    filterTopics();
    clearSearch.addEventListener('click', () => {
        search.value = '';
        filterTopics();
        search.focus();
    });
    form.querySelector('[data-reset-filters]').addEventListener('click', () => {
        search.value = '';
        [scope, level].forEach((filter) => {
            const first = filter.querySelector('input');
            first.checked = true;
            first.dispatchEvent(new Event('change', { bubbles: true }));
        });
        filterTopics();
    });
    form.querySelector('[data-clear-selection]').addEventListener('click', () => {
        form.querySelectorAll('input[name="topic_ids[]"]').forEach((checkbox) => {
            checkbox.checked = false;
            checkbox.dispatchEvent(new Event('change'));
        });
    });
});

import { initializeCheckSession } from './check-session';
document.querySelectorAll('[data-check-session]').forEach(initializeCheckSession);


document.querySelectorAll('[data-topic-browse-filters]').forEach((form) => {
    const search = form.querySelector('[data-browse-search]');
    const clear = form.querySelector('[data-browse-clear]');
    const error = form.querySelector('[data-browse-error]');
    const results = document.querySelector('[data-topic-browse-results]');
    let timer;
    let activeRequest;
    let generation = 0;
    let restoring = false;

    function cancelPending() {
        clearTimeout(timer);
        activeRequest?.abort();
        generation++;
    }

    async function updateResults(updateUrl = true) {
        const current = ++generation;
        activeRequest = new AbortController();
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form)).toString();
        error.hidden = true;
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { signal: activeRequest.signal, headers: { 'Accept': 'text/html' } });
            if (!response.ok || new URL(response.url).pathname !== url.pathname) throw new Error('Request failed');
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = parsed.querySelector('[data-topic-browse-results]');
            if (!updated) throw new Error('Missing results');
            if (current !== generation) return;
            results.innerHTML = updated.innerHTML;
            if (updateUrl) history.replaceState(null, '', url);
        } catch (exception) {
            if (exception.name !== 'AbortError' && current === generation) error.hidden = false;
        } finally {
            if (current === generation) results.removeAttribute('aria-busy');
        }
    }

    function schedule(delay = 0) {
        cancelPending();
        clear.hidden = search.value.length === 0;
        timer = setTimeout(() => updateResults(), delay);
    }

    search.addEventListener('input', () => schedule(300));
    clear.addEventListener('click', () => { search.value = ''; schedule(); search.focus(); });
    form.addEventListener('change', (event) => {
        if (!restoring && event.target.matches('input[type="radio"]')) schedule();
    });
    form.addEventListener('submit', (event) => { event.preventDefault(); schedule(); });
    form.querySelector('[data-browse-retry]').addEventListener('click', () => schedule());
    form.querySelector('[data-browse-reset]').addEventListener('click', () => {
        search.value = '';
        restoring = true;
        for (const [name, value] of [['scope', 'system'], ['level', 'A1-A2']]) {
            const input = [...form.querySelectorAll(`input[name="${name}"]`)].find((option) => option.value === value);
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        restoring = false;
        schedule();
    });
    window.addEventListener('popstate', () => window.location.reload());
});
