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
            showFeedback('Тему оформлення оновлено.');
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
            document.querySelectorAll('[data-profile-item]').forEach((item) => {
                item.querySelector('[data-profile-current]').hidden = item.dataset.profileItem !== String(result.profile_id);
            });
            document.querySelectorAll('[data-theme-form]').forEach((themeForm) => {
                themeForm.action = result.theme_url;
            });
            showFeedback('Активний профіль змінено.');
        } catch {
            showFeedback('Не вдалося обрати профіль. Спробуйте ще раз.');
        }
    });
});

document.addEventListener('click', (event) => {
    document.querySelectorAll('[data-theme-menu][open]').forEach((menu) => {
        if (!menu.contains(event.target)) {
            menu.open = false;
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('[data-theme-menu][open]').forEach((menu) => {
            menu.open = false;
            menu.querySelector('summary').focus();
        });
    }
});
