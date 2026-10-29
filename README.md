# English Cards

**English Cards** — вебзастосунок для вивчення англійських слів за допомогою карток. Орієнтований на зручне використання з телефона та початкові рівні англійської (A1–A2).

## Стан проєкту

**У розробці.** Наразі підготовлено базовий Laravel-проєкт із Docker-оточенням. Функціонал карток, авторизація та адміністративна панель ще не реалізовані.

## Заплановані можливості

- **Вивчення:** показ англійського слова разом з українським перекладом і перегляд карток без оцінювання знань.
- **Перевірка:** показ слова з можливістю відкрити переклад та позначити «знаю» / «повторити» кнопками або свайпами.
- **Теми та рівні:** формування наборів карток із кількох тем; добір за рівнем A1–A2.
- **Прогрес:** індивідуальна історія вивчення та повторення слів.
- **Керування словником:** імпорт слів і тем із CSV та адміністративна панель.

Озвучення слів і приклади вживання розглядаються як подальші покращення.

## Технології

- Laravel 13, PHP 8.4.27 (PHP-FPM), Composer 2.
- Nginx, MySQL 8.4.
- Docker Compose; код проєкту зберігається у файловій системі WSL2 Ubuntu (для Windows).

**Примітка:** Node.js / Vite поки не налаштовані як окремий Docker-сервіс. Під час впровадження frontend додамо потрібні команди та конфігурацію.

## Локальне розгортання

**Потрібно:** Git, Docker Engine із Docker Compose; у Windows — Docker Desktop з увімкненою інтеграцією WSL2 Ubuntu. PHP, Composer і MySQL в Ubuntu встановлювати не потрібно.

### 1. Клонувати репозиторій у WSL2

```bash
cd ~/projects
git clone https://github.com/akorotun/english-cards.git
cd english-cards
```

### 2. Створити локальні налаштування

```bash
cp .env.example .env
```

Перевірити значення `APP_URL=http://localhost:8080`, `APP_PORT=8080` та параметри бази даних. Для правильних прав на файли встановити UID/GID поточного користувача Ubuntu:

```bash
sed -i "s/^APP_UID=.*/APP_UID=$(id -u)/; s/^APP_GID=.*/APP_GID=$(id -g)/" .env
```

### 3. Запустити Docker і встановити PHP-залежності

```bash
docker compose up -d --build
docker compose exec app composer install
```

### 4. Ініціалізувати Laravel

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Відкрити **http://localhost:8080**.

> Це інструкція для запуску **вже створеного Laravel-проєкту**. Повторно виконувати створення Laravel через `composer create-project` не потрібно.

## Корисні команди

```bash
# Стан контейнерів
docker compose ps

# Зупинка (без видалення бази даних)
docker compose down

# Повторний запуск
docker compose up -d

# Команди Laravel
docker compose exec app php artisan migrate
docker compose exec app php artisan route:list

# Додавання PHP-пакета
docker compose exec app composer require vendor/package
```

**Увага:** `docker compose down -v` видаляє також volume бази даних — не використовуйте цю команду для звичайної зупинки.

## Налаштування та безпека

- `.env` — локальний файл, **не додається до Git**. Для прикладу налаштувань використовується `.env.example`.
- `APP_KEY` генерується окремо для кожного середовища.
- MySQL доступний застосунку всередині Docker за адресою `db:3306`; порт бази не відкритий назовні.
- Локальне Docker-оточення призначене для **розробки**, не для production.
