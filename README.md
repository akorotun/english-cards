# Laravel Docker Template

Універсальний Docker-шаблон для швидкого створення Laravel-проєктів.

Шаблон дозволяє розгорнути Laravel без локального встановлення PHP, Composer та MySQL.

## 1. Технології

- Laravel 13
- PHP 8.4.27 (PHP-FPM)
- Nginx
- MySQL 8.4
- Composer 2
- Docker Compose

PHP-образ містить основні розширення, необхідні для Laravel.

## 2. Вимоги

На комп'ютері розробника повинні бути:

- Git
- Docker Desktop або Docker Engine із Docker Compose
- WSL2 + Ubuntu (рекомендовано для Windows)

Для Windows рекомендується зберігати проєкти у файловій системі Ubuntu, а не на диску Windows.

PHP, Composer та MySQL встановлювати локально не потрібно.

## 3. Створення нового проєкту

### Крок 1. Створити репозиторій

На GitHub відкрити репозиторій `laravel-docker-template`.

Натиснути **Use this template → Create a new repository**.

Вказати назву нового проєкту.

### Крок 2. Клонувати проєкт

У терміналі WSL2:

```bash
cd ~/projects
git clone <URL_НОВОГО_РЕПОЗИТОРІЮ>
cd <НАЗВА_ПРОЄКТУ>
```

### Крок 3. Запустити автоматичне налаштування

```bash
bash scripts/setup.sh
```

Скрипт автоматично:

1. Перевірить доступність Docker Compose.
2. Визначить UID/GID користувача.
3. Збере PHP-образ.
4. Встановить Laravel 13.
5. Підготує `.env`.
6. Запустить Docker-контейнери.
7. Згенерує `APP_KEY`.
8. Виконає міграції MySQL.

### Крок 4. Відкрити застосунок

Після успішного завершення налаштування:

http://localhost:8080

## 4. Основні команди

Запустити контейнери:

```bash
docker compose up -d
```

Зупинити контейнери:

```bash
docker compose down
```

Переглянути стан:

```bash
docker compose ps
```

Виконати міграції:

```bash
docker compose exec app php artisan migrate
```

Запустити тести:

```bash
docker compose exec app php artisan test
```

Встановити Composer-пакет:

```bash
docker compose exec app composer require vendor/package
```

## 5. Налаштування

Основні параметри зберігаються в `.env`.

| Параметр | Значення за замовчуванням |
|---|---|
| APP_PORT | 8080 |
| PHP_VERSION | 8.4.27 |
| DB_HOST | db |
| DB_PORT | 3306 |
| DB_DATABASE | laravel |
| DB_USERNAME | laravel |

PHP та MySQL працюють усередині Docker.

Параметри `APP_UID` та `APP_GID` використовуються для узгодження прав доступу до файлів.

## 6. Додаткові компоненти

Шаблон містить лише базове оточення Laravel.

За потреби до конкретного проєкту можна додати:

- Авторизацію та реєстрацію.
- Адміністративну панель (наприклад, Filament).
- Redis.
- Queue Worker.
- Laravel Scheduler.
- Mailpit.
- Node.js / Vite для frontend-розробки.

Ці компоненти не встановлюються автоматично.

## 7. Важливо

- Не додавайте `.env` до Git.
- Не запускайте `setup.sh` повторно після успішного встановлення Laravel.
- Для кожного нового проєкту створюйте окремий репозиторій.
- Перед використанням шаблону перевіряйте актуальність версій PHP та Docker-образів.
- Налаштування призначені для локальної розробки, а не для production.
