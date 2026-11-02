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
