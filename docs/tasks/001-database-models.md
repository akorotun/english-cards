# Task 001 — Міграції, Eloquent-моделі та зв'язки English Cards

**Файл:** `docs/tasks/001-database-models.md`  
**Статус:** виконано; Етапи 1–5 перевірено
**Дата створення:** 02.11.2026  
**Проєкт:** English Cards — Laravel 13 / PHP 8.4 / MySQL (InnoDB)  
**Тип:** backend / database  

## 1. Мета та очікуваний результат

Підготувати фундамент БД English Cards: міграції, Eloquent-моделі, зв'язки, обмеження цілісності, необхідну нормалізацію ключів і автоматизовані тести.

**Після виконання всіх етапів:** усі сім прикладних таблиць із погодженої архітектури створюються міграціями; моделі й зв'язки працюють; основні DB-інваріанти покриті тестами на MySQL. **Не** вважати цим реалізованими користувацькі сценарії навчання, авторизації, імпорту, редагування/видалення тем або API синхронізації.

### Джерела вимог (прочитати до початку роботи)

1. `docs/business/english-flashcards-business-logic-v0.4.md` — поведінка та обмеження MVP.
2. `docs/architecture/data-model.md` — **основне джерело істини для схеми БД**, полів, індексів, FK, нормалізації й Eloquent-зв'язків.
3. Фактичний код проєкту та чинні міграції — перевірити перед змінами; не припускати, що в репозиторії вже встановлено auth starter kit або конкретний тестовий фреймворк.

При суперечності між документацією і кодом **не** підлаштовувати бізнес-правила мовчки: описати розбіжність та запропонувати безпечний варіант. Якщо потрібна зміна погодженої архітектури — зупинитися й узгодити її з користувачем.

## 2. Обсяг завдання

**Входить:**

- Таблиці `users`, `learning_profiles`, `words`, `topics`, `topic_word`, `word_progress`, `progress_sync_batches` відповідно до `data-model.md`.
- Моделі `User`, `LearningProfile`, `Word`, `Topic`, `WordProgress`, `ProgressSyncBatch`; для `topic_word` окрема модель не обов'язкова.
- Зв'язки, PK/FK, UNIQUE, індекси, nullable, каскади, casts та технічні налаштування моделей.
- Поле `learning_profiles.theme_preference` (`system` / `light` / `dark`, за замовчуванням `system`), узгоджені допустимі значення та базові тести. Налаштування належить навчальному профілю, а не `users`.
- Єдина нормалізація ключів `word_en_key`, `word_uk_key` при створенні/зміні відповідних значень через передбачений у цьому task шлях запису; тести її узгодженості. Майбутні імпортери слів повинні використовувати той самий механізм. Для тем ключ унікальності за назвою не створюється.
- Фабрики/тестові дані за потреби й тести структури/цілісності на MySQL.

**Не входить:**

- Реєстрація, login, email verification, створення першого профілю через UI/API, Google OAuth.
- Адмінка, CSV-імпорт, CRUD тем/слів, вибір тем, навчальні екрани, Vue, IndexedDB.
- UI-перемикач теми, CSS-змінні, застосування світлої/темної теми на клієнті, інтеграція з `prefers-color-scheme` та збереження UI-стану в браузері. Це окремий frontend/UI-task.
- Сервіс заміни картки у темі, автоматичне очищення осиротілих `words` під час видалення теми, транзакційний механізм блокувань для такого очищення. Ці **погоджені бізнес-правила залишаються обов'язковими**, але реалізуються окремим task разом із відповідними операціями. Не видавати простий FK cascade за повну реалізацію очищення сиріт.
- Обробник пакетів прогресу, HTTP API, підрахунок `knowledge_level`, ідемпотентна синхронізація за UUID — зараз лише схема та модель `progress_sync_batches`.
- Будь-які зміни production або деплой.

## 3. Правило виконання task — обов'язково для агента

**Виконувати строго поетапно. Одна ітерація = один ПОВНИЙ етап, а не один пункт усередині етапу.**

1. За командою користувача виконати **всі доступні пункти тільки поточного етапу**: реалізацію, передбачені перевірки й коротку документацію результату. Не зупинятися після кожного чекбокса етапу для окремого дозволу.
2. **Після завершення або блокування етапу зупинитися.** Наступний етап починати **лише після нової явної команди** користувача. Не реалізовувати наступні етапи наперед, навіть якщо вони здаються простими.
3. Відмічати `[x]` **тільки для реально виконаних і перевірених пунктів** у цьому task-файлі. Неперевірені й заблоковані залишати `[ ]`.
4. **Безпосередньо під поточним етапом** заповнювати його короткі `Implementation notes`: змінені файли, команди та результати перевірок, відхилення/блокери. Не створювати окремий великий журнал наприкінці task і **не стирати** notes попередніх етапів.
5. Наприкінці ітерації повідомити: що зроблено, які перевірки пройшли/не пройшли, що залишилося; запропонувати **коротку Git commit message англійською**, якщо етап завершений і зміни логічно комітити. **Не робити `git commit`, `git push` без прямої вказівки користувача.**
6. Назвати **наступний етап, не починаючи його**, і коротко оцінити, чи доцільно продовжити в поточній сесії агента або почати нову (з урахуванням обсягу контексту). Перехід до нової сесії — рекомендація, не автоматична дія.
7. Якщо виявлено блокер, небезпечну дію або необхідність змінити узгоджену архітектуру: зафіксувати причину, доступні перевірки та чітке питання/дію для власника; **не** оголошувати етап завершеним і **не** переходити до наступного.
8. **Документація — частина кожного етапу.** Якщо змінюються таблиці, поля, індекси, FK, Eloquent-зв'язки чи правила зберігання, одразу актуалізувати `docs/architecture/data-model.md` у межах того ж етапу. Якщо узгоджено змінюється поведінка для користувача — також актуалізувати `docs/business/english-flashcards-business-logic-v0.4.md`. Не редагувати бізнес-логіку самовільно.
9. Не видаляти початкові вимоги task, попередні `Implementation notes` чи невиконані чекбокси. За потреби пояснити відхилення у notes.

**Безпека середовища:** перевірити, що команди виконуються в локальному Docker Compose-проєкті. Не запускати `migrate:fresh`, `db:wipe` чи інші руйнівні команди на робочій/dev-БД з даними. Для DB-тестів — **окрема ізольована MySQL-база**; `RefreshDatabase` дозволений лише на ній. Не видаляти дані Docker volumes і не змінювати production.

## 4. Етапи реалізації

### Етап 1. Перевірка стану проєкту та основа користувачів/профілів

**Результат етапу:** коректна структура `users` (без `name`) та `learning_profiles` (включно з `theme_preference`), моделі/зв'язок User → LearningProfile, базові тести.

- [x] Перевірити `git status`, версії Laravel/PHP, доступні сервіси Docker Compose, чинні міграції, `User`/factory, тестовий фреймворк і налаштування тестової БД. Не перезаписувати чужі незакомічені зміни.
- [x] Створити окрему тестову базу MySQL, якщо вона відсутня, та налаштувати `.env.testing`. Використовувати наявний контейнер MySQL без створення додаткового. Перевірити налаштування `phpunit.xml` та фактичне підключення до тестової БД перед запуском тестів. Заборонено виконувати руйнівні команди на основній dev-БД.
- [x] Узгодити на рівні коду схему `users` без `name`, не порушуючи безпечно наявні міграції; видалити залежності `name` із `User`/factory у межах цього етапу. Якщо вже є дані або застосовані міграції й потрібна руйнівна зміна — зупинитися й описати варіанти.
- [x] Створити міграцію та модель `learning_profiles`: `id`, `user_id` (FK → `users`, `ON DELETE CASCADE`), `name`, `theme_preference` (`VARCHAR(10)`, `NOT NULL`, `DEFAULT 'system'`), timestamps; потрібний індекс. Додати `User::learningProfiles()` і `LearningProfile::user()`. Не додавати `theme_preference` у `users`.
- [x] Узгодити для `LearningProfile` представлення допустимих значень `theme_preference` (`system`, `light`, `dark`) і відповідні налаштування моделі (наприклад, string-backed enum та cast). Передбачити єдине джерело дозволених значень для Laravel-валідації під час майбутнього створення/редагування профілю; повний UI/API налаштувань зараз не реалізовувати.
- [x] Додати тести: структура без `users.name`, зв'язки, кілька профілів одного користувача, каскадне видалення профілів разом із користувачем; для `theme_preference` — значення `system` за замовчуванням, збереження та читання `light`/`dark`, незалежність налаштувань кількох профілів, відхилення неприпустимого значення через погоджений механізм Laravel-валідації.
- [x] Запустити відповідні міграції й тести **в ізольованій MySQL-БД**; зафіксувати результати. За потреби синхронізувати `data-model.md` із фактичними реалізованими деталями (без зміни бізнес-правил).

**Implementation notes (заповнює агент після виконання етапу):**

- Змінені файли: `AGENTS.md`, `CLAUDE.md`, `boost.json`, `composer.json`, `composer.lock` (обов'язковий Laravel Boost); `app/Models/User.php`, `app/Models/LearningProfile.php`, `app/ThemePreference.php`, `database/factories/UserFactory.php`, `database/factories/LearningProfileFactory.php`, `database/seeders/DatabaseSeeder.php`, дві міграції Етапу 1, `phpunit.xml`, `tests/Feature/LearningProfileTest.php`, `docs/architecture/data-model.md`, цей task; локальний `.env.testing` (ігнорується Git).
- Перевірки та результати: початковий `git status` чистий; Docker Compose `app`, `db`, `nginx` працюють; PHP 8.4.27, Laravel 13.39.0, PHPUnit 12, MySQL 8.4.10; dev-міграції 3 застосовані, `users` мав 0 рядків. Створено порожню окрему `english_cards_test`; `php artisan db:show --env=testing` підтвердив `mysql` / `db` / `english_cards_test`; `php artisan migrate --env=testing` успішно виконав 5 міграцій; `php artisan test --compact tests/Feature/LearningProfileTest.php`: 4 passed, 35 assertions; повний `php artisan test --compact`: 6 passed, 37 assertions; `vendor/bin/pint --dirty --format agent` і `git diff --check` пройшли.
- Відхилення / блокери: початкову вже застосовану міграцію `users` не переписано; `name` прибирає окрема міграція із захистом від видалення колонки, якщо в `users` з'являться дані. Dev-БД не мігрована й не очищена. Блокерів немає.

### Етап 2. Глобальний словник `words`

**Результат етапу:** таблиця й модель `Word` з глобальною унікальністю нормалізованої пари EN + UK.

- [x] Створити `words` точно за `data-model.md`: EN/UK, два нормалізовані key-поля, nullable `image_path`, `example_en`, `example_uk`, timestamps, `UNIQUE(word_en_key, word_uk_key)`; без `user_id`, `profile_id`, `is_system`.
- [x] Реалізувати спільний механізм нормалізації: Unicode NFC, trim, згортання пробілів, регістр за узгодженим стабільним правилом. Забезпечити його використання при записі через модель/визначений спільний шлях; не дублювати нормалізацію в майбутніх імпортерах. За відсутності необхідного PHP-розширення — зафіксувати блокер/запропонувати мінімальну зміну Docker-конфігурації, не змінювати стек мовчки.
- [x] Створити модель `Word`, casts/налаштування, за потреби фабрику. Не реалізовувати CRUD або імпорт.
- [x] Тестами підтвердити: `Apple — Яблуко` та ` apple  — яблуко ` дають один нормалізований ключ і не можуть бути збережені двічі; `light — світло` / `light — легкий` — різні картки; порожні значення відхиляються погодженим шляхом запису.
- [x] Запустити міграції та тести на MySQL; внести лише потрібні уточнення в `data-model.md` і записати результат.

**Implementation notes (заповнює агент після виконання етапу):**

- Змінені файли: `app/Models/Word.php`, `app/TextKeyNormalizer.php`, `database/factories/WordFactory.php`, `database/migrations/2026_11_02_032448_create_words_table.php`, `tests/Feature/WordTest.php`, `docs/architecture/data-model.md`, цей task.
- Перевірки та результати: `docker compose ps` — локальні `app`, `db`, `nginx` працюють; у `app` є `intl` і `mbstring`; `php artisan db:show --env=testing` підтвердив `mysql` / `db` / `english_cards_test` (MySQL 8.4.10); `php artisan migrate --env=testing --no-interaction` — міграція `words` виконана; `php artisan test --compact tests/Feature/WordTest.php` — 6 passed, 60 assertions; `php artisan test --compact` — 12 passed, 97 assertions; Pint і `git diff --check` пройшли.
- Відхилення / блокери: ключі мають `utf8mb4_bin`; спільний нормалізатор використовує NFC, Unicode-пробіли та `MB_CASE_FOLD`; звичайний запис/оновлення через Eloquent `Word` перевіряє дані й обчислює ключі. Прямий SQL оминає нормалізацію застосунку. Dev-БД не мігрована й не очищена; блокерів немає.

### Етап 3. Теми `topics` та розмежування власності

**Результат етапу:** системні теми (`user_id = NULL`) і теми акаунта (`user_id`); однакові назви дозволені, кожну тему визначає її `id`.

- [x] Створити `topics`: `id`, `user_id` nullable FK → `users.id` із CASCADE, `level`, `name`, timestamps; індекси `level` і `user_id`. Не створювати `name_key`, `scope_owner_id` чи UNIQUE за назвою.
- [x] Перевірити фактичну версію MySQL у тестовому середовищі та сумісність схеми з FK `ON DELETE CASCADE`; попередню несумісність `STORED` generated-колонки зафіксовано в історичних notes нижче, а нове рішення власника прибирає таку колонку.
- [x] Створити `Topic`, `Topic::user()`, `User::topics()` для власних приватних тем. Рівень зберігати в узгодженому стабільному регістрі (`A1-A2`, `B1` тощо); назву тримати без нормалізованого ключа унікальності.
- [x] Протестувати: однакові назви системних тем на одному рівні та приватних тем одного власника/рівня дозволені й мають різні `id`; однакові назви на різних рівнях або в різних акаунтах дозволені; видалення користувача каскадно прибирає його приватні теми, системні залишає.
- [x] Запустити міграції та тести на MySQL; зафіксувати результати й оновити `data-model.md` у разі обґрунтованих деталей реалізації.

**Implementation notes (заповнює агент після виконання етапу):**

Попередні notes нижче описують заблоковану спробу до нового рішення власника; вони збережені як історія. Нове рішення дозволяє повторювані назви тем і прибирає generated-колонку та UNIQUE за назвою.

- Змінені файли: `docs/architecture/data-model.md`, цей task. Чернетки `Topic`/міграції/фабрики/тесту й зв'язку `User::topics()` прибрано після виявлення несумісності, щоб невдала міграція не створила часткову таблицю в dev-БД.
- Перевірки та результати: локальні Docker Compose-сервіси працюють; `php artisan db:show --env=testing` підтвердив MySQL 8.4.10 і окрему `english_cards_test`; `php artisan migrate --env=testing --no-interaction` з погодженими `STORED` і `ON DELETE CASCADE` завершився `SQLSTATE[HY000] 1215` під час додавання FK. Порожню часткову `topics` видалено лише з тестової БД. Тимчасова `topics_schema_probe` з `VIRTUAL` generated-колонкою, тим самим виразом, `UNIQUE` та FK `ON DELETE CASCADE` успішно створилася; дубль системної теми залишив один рядок; probe-таблицю видалено. Автоматизовані тести Етапу 3 ще не запускалися.
- Відхилення / блокери: [MySQL 8.4 забороняє каскадний FK на базовій колонці STORED generated-колонки](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html). Потрібне рішення власника: погодити для `scope_owner_id` `VIRTUAL` замість `STORED` зі збереженням `COALESCE(user_id, 0)`, унікального індексу й `ON DELETE CASCADE`, або запропонувати інший еквівалентний DB-варіант. Етап не завершено; до Етапу 4 не переходити.

Після нового рішення власника:

- Змінені файли: `app/Models/Topic.php`, `app/Models/User.php`, `database/factories/TopicFactory.php`, `database/migrations/2026_11_02_035818_create_topics_table.php`, `tests/Feature/TopicTest.php`, `docs/architecture/data-model.md`, `docs/business/english-flashcards-business-logic-v0.4.md`, цей task.
- Перевірки та результати: `php artisan db:show --env=testing` — MySQL 8.4.10 / `english_cards_test`; `php artisan migrate --env=testing --no-interaction` — нова міграція `topics` пройшла; `php artisan test --compact tests/Feature/TopicTest.php` — 6 passed, 51 assertions; повний `php artisan test --compact` — 18 passed, 148 assertions; Pint і `git diff --check` пройшли. `migrate:status --env=testing` після тестів підтвердив усі 7 міграцій як Ran.
- Відхилення / блокери: старе правило унікальності тем замінено явним рішенням власника; `name_key`, `scope_owner_id` і UNIQUE за назвою відсутні. Для майбутнього ідемпотентного CSV-імпорту треба окремо визначити зіставлення рядків із `topics.id`, бо назва й рівень не ідентифікують тему. Dev-БД не мігрована й не очищена. Блокерів Етапу 3 більше немає.

### Етап 4. `topic_word`, `word_progress` та Eloquent-зв'язки

**Результат етапу:** зв'язок many-to-many між темами та словами й незалежний прогрес кожного навчального профілю.

- [x] Створити `topic_word`: FK `topic_id`, `word_id` з CASCADE, складений PK `(topic_id, word_id)`, індекс `word_id`; без окремого `id`.
- [x] Створити `word_progress`: `id`, `learning_profile_id`/`word_id` з CASCADE, `knowledge_level` (`0..3`, default 0), timestamps, `UNIQUE(learning_profile_id, word_id)` і необхідні індекси; DB CHECK — якщо підтримує фактична MySQL згідно з `data-model.md`.
- [x] Створити `WordProgress` і завершити Eloquent-зв'язки `Word::topics()`, `Topic::words()`, `Word::progress()`, `LearningProfile::wordProgress()`, `WordProgress::word()` / `learningProfile()` (дотримуватися погодженої семантики навіть якщо фактичні назви методів оберете трохи інакші; відобразити це в `data-model.md`).
- [x] Тести: слово в кількох темах без дублювання pivot; дублікати `topic_word`/`word_progress` заборонені; різні профілі мають незалежний прогрес для одного слова; каскади FK при **прямому видаленні** слова, теми чи профілю працюють; відсутній прогрес трактується як рівень `0` у майбутній бізнес-логіці, а не як обов'язок завчасно створювати рядки.
- [x] **Окремо зафіксувати межу:** каскадне видалення `topic_word` при видаленні теми **не видаляє автоматично сирітські `words`**. Не реалізовувати очищення сиріт без відповідного сервісного task і не позначати це бізнес-правило реалізованим.
- [x] Запустити тести на MySQL та зафіксувати результати/потрібні уточнення документації.

**Implementation notes (заповнює агент після виконання етапу):**

- Змінені файли: `database/migrations/2026_11_02_040706_create_topic_word_table.php`, `database/migrations/2026_11_02_040707_create_word_progress_table.php`, `app/Models/WordProgress.php`, `app/Models/Word.php`, `app/Models/Topic.php`, `app/Models/LearningProfile.php`, `database/factories/WordProgressFactory.php`, `tests/Feature/TopicWordTest.php`, `tests/Feature/WordProgressTest.php`, `docs/architecture/data-model.md`, цей task.
- Перевірки та результати: локальні Docker Compose `app`, `db`, `nginx` працюють; `php artisan db:show --env=testing` підтвердив MySQL 8.4.10 / `english_cards_test`; `php artisan migrate --env=testing --no-interaction` застосував обидві міграції; `php artisan test --compact tests/Feature/TopicWordTest.php` — 4 passed, 28 assertions; `php artisan test --compact tests/Feature/WordProgressTest.php` — 6 passed, 43 assertions; повний `php artisan test --compact` після Pint — 28 passed, 219 assertions; Pint і `git diff --check` пройшли.
- Відхилення / блокери: MySQL 8.4.10 підтримує й застосовує `word_progress_knowledge_level_check` (`0..3`); окрема модель `WordProgress` явно використовує таблицю `word_progress`. Відсутність прогресу залишається допустимою і в майбутній бізнес-логіці означає ефективний рівень 0. Пряме видалення теми прибирає `topic_word`, але не видаляє осиротілі `words`; сервіс очищення сиріт лишається окремим task. Dev-БД не мігрована й не очищена; блокерів немає.

### Етап 5. `progress_sync_batches` та загальна перевірка схеми

**Результат етапу:** готова модель технічного обліку пакетів і перевірена цілісність усіх семи прикладних таблиць; база готова для наступних task.

- [x] Створити `progress_sync_batches` точно за `data-model.md`: `session_uuid` (`CHAR(36)`, PK), `learning_profile_id` (FK → профіль, CASCADE), `payload_hash` (`CHAR(64)`), nullable `result_json` (`JSON`), `processed_at`, індекс `(learning_profile_id, processed_at)`.
- [x] Створити `ProgressSyncBatch` із коректним UUID primary key (string, non-incrementing), без стандартних timestamps, потрібними casts; додати зв'язки `LearningProfile::progressSyncBatches()` і `ProgressSyncBatch::learningProfile()`.
- [x] Перевірити DB-обмеження: повторний UUID не вставляється, FK працює, видалення профілю каскадно прибирає batches; JSON/час/хеш коректно зберігаються. **Не** реалізовувати API, обчислення прогресу чи ідемпотентний сервіс зараз.
- [x] Запустити **повний актуальний набір автоматизованих тестів** на ізольованій MySQL-БД та перевірити міграції з чистого стану тестової БД (без дій над dev/prod даними).
- [x] Зіставити всі сім таблиць, ключові індекси, FK, моделі та зв'язки з `docs/architecture/data-model.md`, зокрема `learning_profiles.theme_preference` (`system` за замовчуванням; `system` / `light` / `dark`); скоригувати документ **лише відповідно до погодженої реалізації** й зафіксувати, які поведінкові сервіси залишаються на майбутні task.
- [x] Підсумувати виконання task і запропонувати commit message англійською; не створювати коміт без команди користувача.

**Implementation notes (заповнює агент після виконання етапу):**

- Змінені файли: `database/migrations/2026_11_02_042202_create_progress_sync_batches_table.php`, `app/Models/ProgressSyncBatch.php`, `app/Models/LearningProfile.php`, `database/factories/ProgressSyncBatchFactory.php`, `tests/Feature/ProgressSyncBatchTest.php`, `docs/architecture/data-model.md`, цей task.
- Перевірки та результати: локальні Docker Compose `app`, `db`, `nginx` працюють; `php artisan db:show --env=testing` підтвердив MySQL 8.4.10 / окрему `english_cards_test`; `php artisan migrate:fresh --env=testing --no-interaction` успішно виконав усі 10 міграцій лише на тестовій БД; `php artisan migrate:status --env=testing --no-interaction` показав усі як Ran; `php artisan test --compact tests/Feature/ProgressSyncBatchTest.php` — 5 passed, 46 assertions; повний `php artisan test --compact` після Pint — 33 passed, 265 assertions; `vendor/bin/pint --dirty --format agent` пройшов. Метадані `information_schema` підтвердили всі сім таблиць, їхні ключові індекси та FK з CASCADE; попередні тести підтверджують `theme_preference` за замовчуванням та допустимі значення.
- Відхилення / блокери: немає. Dev-БД не очищена й не мігрована. Готова лише DB-основа; авторизація, імпорт, операції редагування/видалення карток і тем з очищенням сиріт, API та транзакційний сервіс синхронізації залишаються окремими task.

## 5. Критерії завершення всього task (Definition of Done)

Task можна вважати завершеним лише тоді, коли:

- У **кожному** етапі виконані всі обов'язкові пункти та заповнені його `Implementation notes` (або окремо задокументовано й погоджено зміну обсягу).
- Усі погоджені прикладні таблиці, обмеження, каскади та зв'язки реалізовані; автоматизовані перевірки пройдені **на MySQL**.
- Не змінено без узгодження принципи: глобальні `words`, власність тільки у `topics`, прогрес окремо за `learning_profile_id`, UUID batches, відсутність `users.name`; налаштування оформлення `theme_preference` належить `learning_profiles`, а не `users`.
- Для `learning_profiles.theme_preference` реалізовані значення `system` / `light` / `dark`, значення за замовчуванням `system`, налаштування моделі та базові тести. **Перемикач теми й оформлення інтерфейсу не входять у цей task.**
- `docs/architecture/data-model.md` відповідає **фактичній** структурі реалізації; за потреби погоджених змін користувацької поведінки оновлено й бізнес-логіку.
- У підсумковому повідомленні чітко розмежовано **готову DB-основу** та **не реалізовані в цьому task** сервіси імпорту, видалення сиріт, редагування карток, авторизації й синхронізації.

---

**Початкова команда для агента:** «Прочитай `docs/tasks/001-database-models.md` та пов'язані документи. Виконай **повністю тільки Етап 1** за правилом виконання task. Після перевірок заповни `Implementation notes` під Етапом 1 та зупинися».
