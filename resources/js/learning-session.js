export function speakEnglish(text, browser, onError) {
    if (!browser.speechSynthesis || typeof browser.SpeechSynthesisUtterance !== 'function') {
        onError();
        return;
    }

    try {
        const utterance = new browser.SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US';
        const voice = browser.speechSynthesis.getVoices().find((item) => /^en(?:-|$)/i.test(item.lang));
        if (voice) utterance.voice = voice;
        utterance.onerror = (event) => {
            if (event.error !== 'canceled' && event.error !== 'interrupted') onError();
        };
        browser.speechSynthesis.cancel();
        browser.speechSynthesis.speak(utterance);
    } catch {
        onError();
    }
}

export function createLearningSession(cards, random = Math.random) {
    let order = [];
    let index = 0;

    function restart() {
        order = [...cards];
        for (let i = order.length - 1; i > 0; i--) {
            const j = Math.floor(random() * (i + 1));
            [order[i], order[j]] = [order[j], order[i]];
        }
        index = 0;
    }

    restart();
    return {
        current: () => order[index],
        position: () => index,
        count: () => order.length,
        completed: () => index === order.length,
        previous: () => { if (index > 0) index--; },
        next: () => { if (index < order.length) index++; },
        restart,
    };
}

export function initializeLearningSession(root) {
    const session = createLearningSession(JSON.parse(root.dataset.cards));
    const cardScreen = root.querySelector('[data-learning-card-screen]');
    const complete = root.querySelector('[data-learning-complete]');
    const previous = root.querySelector('[data-learning-previous]');
    const next = root.querySelector('[data-learning-next]');
    const card = root.querySelector('[data-learning-card]');
    const audioFeedback = root.querySelector('[data-learning-audio-feedback]');
    const clearAudioFeedback = () => { audioFeedback.hidden = true; };

    function render() {
        clearAudioFeedback();
        const finished = session.completed();
        cardScreen.hidden = finished;
        complete.hidden = !finished;
        if (finished) {
            complete.focus();
            return;
        }
        root.querySelector('[data-learning-en]').textContent = session.current().word_en;
        root.querySelector('[data-learning-uk]').textContent = session.current().word_uk;
        root.querySelector('[data-learning-counter]').textContent = `${session.position() + 1} / ${session.count()}`;
        previous.disabled = session.position() === 0;
        next.textContent = session.position() === session.count() - 1 ? 'Завершити →' : 'Далі →';
    }

    root.querySelector('[data-learning-speak]').addEventListener('click', () => {
        clearAudioFeedback();
        speakEnglish(session.current().word_en, window, () => {
            audioFeedback.textContent = 'Не вдалося відтворити вимову. Ви можете продовжити перегляд карток.';
            audioFeedback.hidden = false;
        });
    });
    previous.addEventListener('click', () => { session.previous(); render(); });
    next.addEventListener('click', () => { session.next(); render(); });
    root.querySelector('[data-learning-restart]').addEventListener('click', () => { session.restart(); render(); next.focus(); });
    document.addEventListener('keydown', (event) => {
        if (session.completed() || event.altKey || event.ctrlKey || event.metaKey || event.target.closest('input, textarea, select, [contenteditable="true"]')) return;
        if (event.key === 'ArrowLeft' && !previous.disabled) { event.preventDefault(); previous.click(); }
        if (event.key === 'ArrowRight') { event.preventDefault(); next.click(); }
    });
    let touchStart = null;
    card.addEventListener('touchstart', (event) => {
        if (event.touches.length === 1) touchStart = { x: event.touches[0].clientX, y: event.touches[0].clientY };
    }, { passive: true });
    card.addEventListener('touchend', (event) => {
        if (!touchStart || event.changedTouches.length !== 1) return;
        const dx = event.changedTouches[0].clientX - touchStart.x;
        const dy = event.changedTouches[0].clientY - touchStart.y;
        touchStart = null;
        if (Math.abs(dx) < 50 || Math.abs(dx) <= Math.abs(dy) * 1.5) return;
        if (dx < 0) next.click();
        else if (!previous.disabled) previous.click();
    }, { passive: true });
    card.addEventListener('touchcancel', () => { touchStart = null; });
    render();
    root.querySelector('[data-learning-content]').hidden = false;
}
