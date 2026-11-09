import { freezeCheckState, initializeCheckSession, openCheckStorage, writeCheckState } from './check-session.js';

export function readCheckSessions(db, userId) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('sessions', 'readonly');
        const request = tx.objectStore('sessions').getAll();
        tx.oncomplete = () => resolve(request.result.filter((state) => state.user_id === userId)
            .sort((a, b) => (a.created_at ?? 0) - (b.created_at ?? 0) || a.session_uuid.localeCompare(b.session_uuid)));
        tx.onabort = () => reject(tx.error || new Error('Storage read failed'));
        tx.onerror = () => {};
    });
}

export function deleteCheckSession(db, uuid) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('sessions', 'readwrite');
        tx.objectStore('sessions').delete(uuid);
        tx.oncomplete = () => resolve();
        tx.onabort = () => reject(tx.error || new Error('Storage delete failed'));
        tx.onerror = () => {};
    });
}

export async function sendCheckBatch(payload, { url, csrf, fetch: send = globalThis.fetch }) {
    const response = await send(url, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(payload), signal: AbortSignal.timeout(15000),
    });
    if (!response.ok) {
        const error = new Error(`Sync failed: ${response.status}`);
        error.status = response.status;
        throw error;
    }
    const result = await response.json();
    if (result.status !== 'synced' || result.session_uuid !== payload.session_uuid
        || result.learning_profile_id !== payload.learning_profile_id
        || !Array.isArray(result.skipped_word_ids) || !Number.isInteger(result.applied)) {
        throw new Error('Invalid synchronization acknowledgement');
    }
    return result;
}

/** Caller holds the profile's Web Lock throughout recovery, delivery and deletion. */
export async function synchronizeCheckProfile(db, userId, profileId, options) {
    const sessions = (await readCheckSessions(db, userId)).filter((state) => state.learning_profile_id === profileId);
    const results = [];
    for (const session of sessions) {
        const frozen = freezeCheckState(session);
        if (!session.sync_payload) await writeCheckState(db, frozen);
        const result = await sendCheckBatch(frozen.sync_payload, options);
        await deleteCheckSession(db, frozen.session_uuid);
        results.push(result);
    }
    return results;
}

export function checkProfileLock(userId, profileId) {
    return `english-cards-check:${userId}:${profileId}`;
}

export function initializeCheckSync(root, sessionRoot = null) {
    if (!root) return;
    const userId = Number(root.dataset.userId);
    const activeProfile = sessionRoot ? Number(sessionRoot.dataset.profileId) : null;
    const message = root.querySelector('[data-check-sync-message]');
    const retry = root.querySelector('[data-check-sync-retry]');
    const options = { url: root.dataset.url, csrf: root.dataset.csrf };
    const failures = new Map();
    const inFlight = new Map();
    let recoveryMessage = '';
    let recovering = false;
    let sessionActive = false;
    let starting = false;
    let pageClosed = false;
    let db;
    let releaseSession;
    const lifetime = new Promise((resolve) => { releaseSession = resolve; });
    window.addEventListener('pagehide', () => { pageClosed = true; releaseSession(); }, { once: true });
    window.addEventListener('pageshow', (event) => { if (event.persisted) window.location.reload(); });

    function report(text) {
        root.hidden = false;
        const failure = failures.values().next().value;
        message.textContent = failure ?? text;
        retry.hidden = failures.size === 0;
        retry.disabled = recovering || inFlight.size > 0 || starting;
    }

    function failureMessage(error) {
        const detail = error.status === 409 ? 'Сервер виявив конфлікт пакета.'
            : [401, 403, 419].includes(error.status) ? 'Оновіть сторінку та перевірте вхід до акаунта.'
                : error.status === 422 || error.status === 404 ? 'Сервер не прийняв пакет. Перевірте доступність навчального профілю.'
                    : 'Не вдалося синхронізувати відповіді.';
        return `${detail} Відповіді залишаються в цьому браузері. Нова перевірка цього профілю доступна після підтвердження сервера.`;
    }

    function syncProfile(profileId, sessionUuid = null) {
        if (inFlight.has(profileId)) return inFlight.get(profileId);
        const pending = (async () => {
            if (!sessionUuid) report('Синхронізація збережених відповідей…');
            try {
                const results = await synchronizeCheckProfile(db, userId, profileId, options);
                if (sessionUuid && !results.some((result) => result.session_uuid === sessionUuid)) {
                    throw new Error('Missing synchronization acknowledgement');
                }
                failures.delete(profileId);
                const skipped = results.reduce((total, result) => total + result.skipped_word_ids.length, 0);
                const skippedMessage = skipped ? ` Недоступних або видалених слів пропущено: ${skipped}.` : '';
                if (!sessionUuid) {
                    if (results.length) recoveryMessage = `Попередні відповіді синхронізовано.${skippedMessage}`;
                    if (recoveryMessage || failures.size) report(recoveryMessage);
                    else root.hidden = true;
                }
                return { synced: true, message: `Прогрес синхронізовано.${skippedMessage}` };
            } catch (error) {
                const text = failureMessage(error);
                if (!sessionUuid) {
                    failures.set(profileId, text);
                    report('');
                }
                return { synced: false, message: text };
            } finally {
                inFlight.delete(profileId);
                retry.disabled = recovering || starting || inFlight.size > 0;
            }
        })();
        inFlight.set(profileId, pending);
        return pending;
    }

    async function startSession() {
        if (starting || sessionActive || pageClosed) return;
        starting = true;
        let ready;
        const initialized = new Promise((resolve) => { ready = resolve; });
        window.navigator.locks.request(checkProfileLock(userId, activeProfile), { ifAvailable: true }, async (lock) => {
            try {
                if (!lock) {
                    failures.set(activeProfile, 'Цей профіль уже проходить перевірку або синхронізацію в іншій вкладці. Завершіть її та повторіть спробу.');
                    report('');
                    return;
                }
                if (pageClosed || !(await syncProfile(activeProfile)).synced || pageClosed) return;
                sessionActive = await initializeCheckSession(sessionRoot, {
                    db, sync: (uuid) => syncProfile(activeProfile, uuid), pageClosed: () => pageClosed,
                });
                starting = false;
                ready();
                if (sessionActive) await lifetime;
            } finally {
                starting = false;
                ready();
                retry.disabled = recovering || inFlight.size > 0;
            }
        }).catch((error) => {
            starting = false;
            failures.set(activeProfile, failureMessage(error));
            report('');
            ready();
        });
        await initialized;
    }

    async function recover() {
        if (recovering || pageClosed) return;
        recovering = true;
        retry.disabled = true;
        try {
            if (!window.navigator.locks) throw new Error('Web Locks unavailable');
            db ??= await openCheckStorage(window.indexedDB);
            failures.delete('storage');
            const sessions = await readCheckSessions(db, userId);
            for (const profileId of new Set(sessions.map((state) => state.learning_profile_id))) {
                if (profileId === activeProfile) continue;
                await window.navigator.locks.request(checkProfileLock(userId, profileId), { ifAvailable: true }, async (lock) => {
                    if (lock && !pageClosed) await syncProfile(profileId);
                });
            }
            if (sessionRoot && !pageClosed && !sessionActive) await startSession();
        } catch {
            failures.set('storage', 'Не вдалося відкрити локальне сховище або захист між вкладками. Використайте сучасний браузер через HTTPS або localhost. Збережені відповіді не видалено.');
            report('');
        } finally {
            recovering = false;
            retry.disabled = inFlight.size > 0 || starting;
        }
    }

    retry.addEventListener('click', recover);
    return recover();
}
