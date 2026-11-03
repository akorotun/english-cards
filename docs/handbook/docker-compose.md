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
