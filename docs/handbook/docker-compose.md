# 🐳 Docker Compose — шпаргалка команд для Laravel

**Середовище:** Windows + WSL2 Ubuntu + Docker Desktop + PhpStorm.

**Важливо:** усі команди виконуються в терміналі WSL2 Ubuntu, з кореня проєкту, де розташований `compose.yaml` або `docker-compose.yml`.

## 1. Запуск та зупинка контейнерів

**Запустити всі контейнери:**
```bash
docker compose up -d
```

**Зупинити та видалити контейнери (дані БД зберігаються):**
```bash
docker compose down
```

**Тільки зупинити контейнери:**
```bash
docker compose stop
```

**Запустити раніше зупинені контейнери:**
```bash
docker compose start
```

**Перебудувати образи та запустити контейнери:**
```bash
docker compose up -d --build
```
Використовувати після змін Dockerfile або залежностей системного рівня. Для звичайних змін PHP-коду не потрібно.

---

## 2. Перевірка контейнерів

**Переглянути стан контейнерів:**
```bash
docker compose ps
```

**Переглянути всі сервіси проєкту:**
```bash
docker compose config --services
```

**Переглянути логи всіх контейнерів:**
```bash
docker compose logs -f
```

**Переглянути логи конкретного сервісу:**
```bash
docker compose logs -f app
```

Вийти з перегляду логів: `Ctrl+C`.

---

## 3. Laravel Artisan

**Загальний формат:**
```bash
docker compose exec app php artisan <команда>
```

**Запустити міграції:**
```bash
docker compose exec app php artisan migrate
```

**Перевірити стан міграцій:**
```bash
docker compose exec app php artisan migrate:status
```

**Переглянути маршрути:**
```bash
docker compose exec app php artisan route:list
```

**Очистити кеш Laravel:**
```bash
docker compose exec app php artisan optimize:clear
```

**Запустити Laravel Tinker:**
```bash
docker compose exec app php artisan tinker
```

**Згенерувати APP_KEY (під час початкового налаштування):**
```bash
docker compose exec app php artisan key:generate
```

---

## 4. Тести Laravel

**Запустити всі тести:**
```bash
docker compose exec app php artisan test
```

**Тільки Feature-тести:**
```bash
docker compose exec app php artisan test --testsuite=Feature
```

**Тільки Unit-тести:**
```bash
docker compose exec app php artisan test --testsuite=Unit
```

**Запустити конкретний тест:**
```bash
docker compose exec app php artisan test --filter=UserRegistrationTest
```

**Важливо:** перед запуском тестів, які працюють із БД, переконатися, що налаштована окрема тестова база даних або SQLite in-memory. Тести не повинні змінювати робочу БД.

---

## 5. Composer

**Встановити залежності з composer.lock:**
```bash
docker compose exec app composer install
```

**Оновити автозавантаження класів:**
```bash
docker compose exec app composer dump-autoload
```

**Перевірити версію Composer:**
```bash
docker compose exec app composer --version
```

**Перевірити версію PHP:**
```bash
docker compose exec app php -v
```

---

## 6. Робота всередині контейнера

**Відкрити термінал PHP-контейнера:**
```bash
docker compose exec app sh
```

Тепер можна виконувати команди без `docker compose exec app`:

```bash
php artisan migrate
php artisan test
composer install
```

**Вийти з контейнера:**
```bash
exit
```

---

## 7. Назви сервісів у різних проєктах

| Призначення | English Cards | SimpleCV |
|---|---|---|
| PHP + Laravel | app | php84 |
| Вебсервер | nginx | nginx |
| MySQL | db | mysql |

Назви визначаються файлом Docker Compose.

**English Cards:**
```bash
docker compose exec app php artisan test
```

**SimpleCV:**
```bash
docker compose exec php84 php artisan test
```

Якщо не пам'ятаєш назви сервісів:
```bash
docker compose config --services
```

**Універсальний формат:**
```bash
docker compose exec <service> <command>
```

Наприклад:
```bash
docker compose exec app php artisan migrate
```

---

## 8. Небезпечні команди ⚠️

**НЕ виконувати без необхідності:**
```bash
docker compose down -v
```

Команда видаляє контейнери разом з іменованими томами (volumes), визначеними Compose, зокрема том MySQL із даними.

Звичайна команда:
```bash
docker compose down
```
дані в іменованих томах НЕ видаляє.

Також обережно з:
```bash
docker system prune
docker volume prune
```

Ці команди можуть видаляти невикористовувані Docker-ресурси, зокрема ресурси інших проєктів.

---

## 9. Щоденний сценарій роботи

**Початок роботи:**
```bash
docker compose up -d
```

**Scheduler:**
```bash
docker compose exec app php artisan schedule:work --no-interaction
```

**Перевірка:**
```bash
docker compose ps
```

**Після змін у Laravel:**
```bash
docker compose exec app php artisan test
```

**Якщо додані міграції:**
```bash
docker compose exec app php artisan migrate
```

**Завершення роботи:**
```bash
docker compose down
```

---

## 10. Наш стандарт для нових Laravel-проєктів

Для всіх нових проєктів використовуємо:

- Docker Compose без Laravel Sail.
- Сервіси: `app`, `nginx`, `db`.
- PHP, Composer, MySQL — усередині Docker.
- Код проєкту — у файловій системі Ubuntu WSL2.
- Команди запускаються з термінала PhpStorm.
- Основний запуск: `docker compose up -d`.
- Основна зупинка: `docker compose down`.
- Artisan: `docker compose exec app php artisan ...`.
- Тести: `docker compose exec app php artisan test`.
- Перевірити, що frontend збирається: `docker compose run --rm --no-deps node npm run build`.

**Головне правило:** для виконання команд усередині запущеного PHP-контейнера використовуємо `docker compose exec app`.

Це наш базовий стандарт для всіх наступних Laravel-проєктів.

---

## 11. Браузерна перевірка English Cards через Chromium і CDP

Цей спосіб відкриває реальний застосунок у **headless Chromium** через Chrome DevTools Protocol (CDP). Він не потребує нових npm/Composer-залежностей або змін `compose.yaml`. Команди нижче запускаються з кореня репозиторію у WSL2; Node.js запускається тільки через наявний сервіс `node`.

**Передумови:** сервіси `app`, `nginx` і `node` вже працюють (`docker compose ps`); образ `gotenberg/gotenberg:8.34.0-chromium` уже існує локально (`docker image inspect gotenberg/gotenberg:8.34.0-chromium`). `--pull=never` не завантажує образ. Якщо образу чи сервісу немає, самостійно не встановлювати інструменти й не змінювати Docker-конфігурацію: погодити потрібний спосіб із власником. Потрібен Node.js із вбудованими `fetch` і `WebSocket` (у наявному образі — Node 24).

**Мережа:** тимчасовий Chromium використовує мережевий простір наявного контейнера `node` (`--network container:…`), а CDP слухає тільки `127.0.0.1:19237` усередині цього простору. Node-скрипт піднімає тимчасовий TCP-проксі **тільки на `127.0.0.1:8080`** до `nginx:80`. Це дає Chromium доступ до `http://localhost:8080` і до абсолютних посилань Laravel, які використовують цей origin; наявний Vite у `node` доступний через `localhost:5173`. Це не опубліковує CDP або проксі на зовнішній мережевий інтерфейс. Порти 8080 і 19237 мають бути вільними саме в контейнері `node`; за конфлікту завершити відповідний попередній процес або узгодити інші локальні порти й origin, не змінюючи Compose.

**Межі та безпека:** приклад відкриває гостьові сторінки dev-застосунку і вводить вигадану адресу, не надсилаючи форму. Laravel із database-сесіями може створити технічний запис гостьової сесії в dev-БД. Не вводити реальні облікові дані, не створювати реальні навчальні дані та не запускати руйнівних DB-команд. Перевірки, які створюють або змінюють дані, виконувати лише в окремому тестовому середовищі з перевіреною `english_cards_test`, а не змінювати конфігурацію чи дані dev-застосунку. `--no-sandbox` вимикає внутрішню ізоляцію Chromium: використовувати тільки для довірених локальних сторінок у тимчасовому контейнері; не відкривати довільні зовнішні сайти й не публікувати CDP. Емуляція viewport і системної теми не замінює тест на фізичному пристрої. Відсутність помилок консолі не доводить коректність усіх сценаріїв.

### Перевірений мінімальний приклад

Скопіюйте весь блок у Bash. Він запускає Chromium у тимчасовому контейнері `--rm`, відкриває головну сторінку, клікає «Увійти», вводить вигаданий email, знімає шість PNG і показує стан консолі. Пастка `trap` зупиняє контейнер навіть при помилці або перериванні. Профіль браузера живе всередині контейнера та зникає разом із ним. Приклад не перевіряє авторизовані сторінки.

```bash
(
set -eu

task_node_id="$(docker compose ps -q node)"
test -n "$task_node_id"
docker image inspect gotenberg/gotenberg:8.34.0-chromium >/dev/null

task_output_dir="$(mktemp -d /tmp/english-cards-cdp-XXXXXX)"
task_prefix="${task_output_dir##*/}"
task_chromium_id=""
cleanup_cdp() {
    if [ -n "$task_chromium_id" ]; then
        docker stop "$task_chromium_id" >/dev/null 2>&1 || true
    fi
}
trap cleanup_cdp EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

task_chromium_id="$(docker run -d --rm --pull=never \
    --network "container:$task_node_id" \
    --entrypoint /usr/bin/chromium \
    gotenberg/gotenberg:8.34.0-chromium \
    --headless --no-sandbox --disable-gpu --disable-dev-shm-usage \
    --disable-background-networking \
    --remote-debugging-address=127.0.0.1 --remote-debugging-port=19237 \
    --user-data-dir=/tmp/english-cards-cdp-profile about:blank)"

docker compose exec -T -e CDP_PREFIX="$task_prefix" node node --input-type=module <<'JS'
import net from 'node:net';
import { mkdir, writeFile } from 'node:fs/promises';

const screenshotDir = `/tmp/${process.env.CDP_PREFIX}`;
await mkdir(screenshotDir); // унікальний каталог; не перезаписує чужі файли

const sockets = new Set();
const proxy = net.createServer((client) => {
    const upstream = net.connect(80, 'nginx');
    sockets.add(client);
    sockets.add(upstream);
    client.on('close', () => sockets.delete(client));
    upstream.on('close', () => sockets.delete(upstream));
    client.on('error', () => upstream.destroy());
    upstream.on('error', () => client.destroy());
    client.pipe(upstream).pipe(client);
});
let ws;
const pending = new Map();
let nextId = 0;
const errors = [];
const consoleMessages = [];
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

try {
    await new Promise((resolve, reject) => {
        proxy.once('error', reject);
        proxy.listen(8080, '127.0.0.1', resolve);
    });

    let targets;
    for (let attempt = 0; attempt < 50; attempt++) {
        try {
            const response = await fetch('http://127.0.0.1:19237/json/list');
            targets = await response.json();
            if (targets.some((target) => target.type === 'page')) break;
        } catch { /* Chromium ще запускається. */ }
        await pause(100);
    }
    const target = targets?.find((item) => item.type === 'page');
    if (!target) throw new Error('CDP недоступний на 127.0.0.1:19237');

    ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
    ws.onmessage = ({ data }) => {
        const message = JSON.parse(data);
        if (message.method === 'Runtime.exceptionThrown') {
            const detail = message.params.exceptionDetails;
            errors.push(detail.exception?.description || detail.text);
        }
        if (message.method === 'Runtime.consoleAPICalled') {
            consoleMessages.push({
                type: message.params.type,
                text: message.params.args.map((arg) => arg.value ?? arg.description ?? '').join(' '),
            });
        }
        const request = pending.get(message.id);
        if (request) {
            clearTimeout(request.timer);
            pending.delete(message.id);
            message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
        }
    };

    function send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = ++nextId;
            const timer = setTimeout(() => {
                pending.delete(id);
                reject(new Error(`CDP timeout: ${method}`));
            }, 8000);
            pending.set(id, { resolve, reject, timer });
            ws.send(JSON.stringify({ id, method, params }));
        });
    }
    async function evaluate(expression) {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
        return result.result.value;
    }
    async function waitFor(expression) {
        for (let attempt = 0; attempt < 80; attempt++) {
            if (await evaluate(expression)) return;
            await pause(100);
        }
        throw new Error(`Елемент або сторінка не з'явилися: ${expression}`);
    }
    async function click(selector) {
        const point = await evaluate(`(() => {
            const element = document.querySelector(${JSON.stringify(selector)});
            if (!element) throw new Error('Елемент не знайдено');
            element.scrollIntoView({ block: 'center' });
            const rect = element.getBoundingClientRect();
            return { x: rect.x + rect.width / 2, y: rect.y + rect.height / 2 };
        })()`);
        for (const type of ['mouseMoved', 'mousePressed', 'mouseReleased']) {
            await send('Input.dispatchMouseEvent', { type, ...point, button: 'left', clickCount: 1 });
        }
    }

    await send('Page.enable');
    await send('Runtime.enable');
    await send('Page.navigate', { url: 'http://localhost:8080/' });
    await waitFor("location.pathname === '/' && document.readyState === 'complete' && !!document.querySelector('a[href$=\"/login\"]')");
    console.log('Головна:', await evaluate('document.title'));
    await click('a[href$="/login"]');
    await waitFor("location.pathname === '/login' && !!document.querySelector('#email')");
    await click('#email');
    await send('Input.insertText', { text: 'ui-test@example.test' });
    console.log('Сторінка:', await evaluate('location.href'));
    console.log('Введене значення:', await evaluate("document.querySelector('#email').value"));

    // Приклади інших дій через CDP: Tab і прокручування коліщатком.
    for (const type of ['keyDown', 'keyUp']) {
        await send('Input.dispatchKeyEvent', { type, key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
    }
    await send('Input.dispatchMouseEvent', { type: 'mouseWheel', x: 150, y: 300, deltaX: 0, deltaY: 120 });
    await send('Input.dispatchMouseEvent', { type: 'mouseWheel', x: 150, y: 300, deltaX: 0, deltaY: -120 });

    for (const width of [320, 390, 1280]) {
        for (const theme of ['light', 'dark']) {
            await send('Emulation.setDeviceMetricsOverride', {
                width, height: 900, deviceScaleFactor: 1, mobile: width < 500,
            });
            await send('Emulation.setEmulatedMedia', {
                features: [{ name: 'prefers-color-scheme', value: theme }],
            });
            await pause(100);
            const pageColor = await evaluate("getComputedStyle(document.documentElement).getPropertyValue('--page').trim()");
            const screenshot = await send('Page.captureScreenshot', { format: 'png' });
            const path = `${screenshotDir}/${width}-${theme}.png`;
            await writeFile(path, Buffer.from(screenshot.data, 'base64'));
            console.log(`${width}px ${theme}: --page=${pageColor}, PNG=${path}`);
        }
    }
    // Демонструє, що події консолі надходять у CDP; справжні повідомлення також потраплять у список.
    await evaluate("console.info('CDP console probe')");
    await pause(100);
    console.log('Консоль:', JSON.stringify(consoleMessages));
    console.log('JavaScript-винятки:', JSON.stringify(errors));
} finally {
    if (ws) ws.close();
    for (const socket of sockets) socket.destroy();
    if (proxy.listening) proxy.close();
}
JS

# Копіюємо лише файли цього запуску в тимчасовий каталог WSL, не в Git.
docker cp "$task_node_id:/tmp/$task_prefix/." "$task_output_dir/"
docker compose exec -T node rm -r "/tmp/$task_prefix"
printf 'Скриншоти: %s\n' "$task_output_dir"
)
```

Приклад використовує реальні координатні події CDP (`Input.dispatchMouseEvent`), а не `element.click()`. Для клавіш використовуйте `Input.dispatchKeyEvent`, для прокручування — `Input.dispatchMouseEvent` із `type: 'mouseWheel'`, для читання DOM — `Runtime.evaluate`; після зміни сторінки дочекайтеся її готовності й заново отримайте координати. Скриншоти через `Page.captureScreenshot` зберігаються у вказаному тимчасовому каталозі WSL, не у репозиторії. `Runtime.consoleAPICalled` збирає виклики консолі, `Runtime.exceptionThrown` — необроблені JavaScript-винятки; мережеві помилки для окремих перевірок потребують додаткових подій `Network`/`Log`.

`prefers-color-scheme` перевіряє **системну** тему гостьової сторінки. Теми, явно встановлені в навчальному профілі, треба окремо перевіряти через інтерфейс у належному ізольованому середовищі. Після запуску не вважайте авторизовані сценарії, мобільні жести чи перевірку справжнього Chrome/телефона виконаними, якщо їх фактично не перевіряли.
