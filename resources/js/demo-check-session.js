import { createLearningSession, speakEnglish } from './learning-session.js';
import { renderCheckTranslation } from './check-session.js';

export function initializeDemoCheckSession(root) {
    const session = createLearningSession(JSON.parse(root.dataset.cards));
    const cardScreen = root.querySelector('[data-demo-check-card-screen]');
    const complete = root.querySelector('[data-demo-check-complete]');
    const next = root.querySelector('[data-demo-check-next]');
    const reveal = root.querySelector('[data-check-reveal]');
    const translation = root.querySelector('[data-check-uk]');
    const audioFeedback = root.querySelector('[data-check-audio-feedback]');
    let revealed = false;

    function render() {
        const finished = session.completed();
        cardScreen.hidden = finished;
        complete.hidden = !finished;
        audioFeedback.hidden = true;
        if (finished) {
            complete.focus();
            return;
        }
        root.querySelector('[data-check-en]').textContent = session.current().word_en;
        renderCheckTranslation(translation, reveal, session.current().word_uk, revealed);
        root.querySelector('[data-demo-check-counter]').textContent = `${session.position() + 1} / ${session.count()}`;
        root.querySelector('[data-demo-check-progress]').value = session.position() + 1;
        next.disabled = !revealed;
    }

    reveal.addEventListener('click', () => { revealed = !revealed; render(); });
    next.addEventListener('click', () => {
        if (!revealed) return;
        session.next();
        revealed = false;
        render();
        if (!session.completed()) reveal.focus();
    });
    root.querySelector('[data-demo-check-restart]').addEventListener('click', () => {
        session.restart();
        revealed = false;
        render();
        reveal.focus();
    });
    root.querySelector('[data-check-speak]').addEventListener('click', () => {
        audioFeedback.hidden = true;
        speakEnglish(session.current().word_en, window, () => {
            audioFeedback.textContent = 'Не вдалося відтворити вимову. Ви можете продовжити перегляд карток.';
            audioFeedback.hidden = false;
        });
    });
    render();
    root.querySelector('[data-demo-check-content]').hidden = false;
}
