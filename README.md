# SCUM Server Manager (SSM) v2.0

> **Enterprise веб-панель управления игровыми серверами SCUM** на базе Laravel 12, Alpine.js, Tailwind CSS и прямого асинхронного RCON/FTP шлюза.

---

## 🌟 Возможности панели

- **Интерактивная RCON-консоль (Interactive CLI)**:
  - Прямой защищённый шлюз выполнения игровых команд в реальном времени через фоновый демон.
  - 238 официальных команд сервера SCUM: игроки, транспорт, спавн, базы, модерация, существа/роботы, экономика и события.
  - Быстрая шпаргалка параметров и вставка команд в консоль в 1 клик.
- **Интеллектуальный каталог игровых объектов**:
  - Свыше 2 400 предметов, вся доступная техника, животные, роботы, NPC и ключевые точки интереса (POI).
  - Умный поиск с поддержкой сленга («калаш», «сишка», «дигл») и русской раскладки.
  - Оптимизированные WebP-иконки для каждого предмета и транспортного средства.
- **Интерактивная карта острова SCUM**:
  - Просмотр расположения игроков онлайн, транспорта и баз в реальном времени.
  - Быстрый телепорт и навигация по координатам.
- **База данных SCUM (SCUM.db & Telemetry)**:
  - Глубокая аналитика игроков: досье, инвентарь, баланс валюты (Cash/Gold), очки славы, время в игре.
  - Инспектор баз и флагов с координатами и элементами строений.
  - Инспектор транспорта с отслеживанием владельцев и координат.
- **Автоматизация и триггеры**:
  - Визуальный конструктор сценариев и расписаний (Schedules).
  - Интеграция с магазином WARGM (автовыдача доната в игре).
  - Airdrop Spawner, система наград и плагин страхования транспорта.
- **Безопасность корпоративного уровня**:
  - Двухфакторная аутентификация (2FA / TOTP с QR-кодом).
  - Защита IP Whitelist с поддержкой CIDR-подсетей.
  - Защита от подбора паролей (Brute-force protection).
  - Полный аудит действий администраторов (Audit Logs).

---

## 📋 Системные требования

| Компонент | Требование | Примечание |
| :--- | :--- | :--- |
| **ОС** | Linux (Ubuntu 22.04+, Debian 11+, Rocky Linux) или Windows 10/11 / Windows Server | Рекомендуется Ubuntu 24.04 LTS |
| **PHP** | `8.2`, `8.3` или `8.4` | Рекомендуется PHP 8.3 или 8.4 |
| **Расширения PHP** | `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`, `openssl`, `bcmath`, `fileinfo`, `zip` | Необходимы для работы базы и RCON |
| **Composer** | `2.x` | Менеджер PHP-пакетов |
| **База данных** | SQLite 3 | **MySQL/PostgreSQL не требуются!** SQLite встроен в PHP |
| **Node.js** *(опционально)* | `18.x` или новее | Требуется только если вы будете менять исходный CSS/JS. Готовые скомпилированные ассеты уже включены в релиз |

### Установка зависимостей на сервере Ubuntu / Debian:
```bash
sudo apt update
sudo apt install -y php8.3-cli php8.3-sqlite3 php8.3-curl php8.3-mbstring php8.3-bcmath php8.3-zip php8.3-xml unzip git curl
# Установка Composer:
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

---

## 🚀 Установка и настройка

### Способ 1. Интерактивная установка в 1 команду (Рекомендуется)

Самый быстрый и удобный способ для любого пользователя. Встроенный мастер сам настроит конфигурацию, создаст файл базы данных, применит таблицы и создаст учетную запись:

```bash
# 1. Склонируйте репозиторий
git clone https://github.com/your-username/scum-server-manager.git /var/www/scum-panel
cd /var/www/scum-panel

# 2. Установите зависимости Composer
composer install --no-dev --optimize-autoloader

# 3. Запустите мастер установки панели
php artisan panel:install
```

**Что делает мастер `panel:install` автоматически:**
1. Копирует `.env.example` в `.env` (если файл еще не создан).
2. Генерирует уникальный ключ шифрования `APP_KEY`.
3. Создает файл локальной базы данных `database/database.sqlite`.
4. Применяет все миграции таблиц (`php artisan migrate --force`).
5. Интерактивно запрашивает логин и пароль первого администратора.

После завершения просто запустите панель:
```bash
php artisan serve
```
Откройте браузер по адресу: **`http://127.0.0.1:8000`**

---

### Способ 2. Автоматическая установка через Composer

Если вы хотите выполнить установку одной стандартной командой Composer:

```bash
cd /var/www/scum-panel
composer run setup
php artisan panel:user
```
Скрипт `setup` последовательно установит PHP-пакеты, настроит `.env`, сгенерирует ключ, создаст файл `database/database.sqlite` и выполнит миграции.

---

### Способ 3. Пошаговая ручная установка (Manual Setup)

Если вам требуется полный контроль над каждым шагом настройки:

#### 1. Клонирование репозитория
```bash
git clone https://github.com/your-username/scum-server-manager.git /var/www/scum-panel
cd /var/www/scum-panel
```

#### 2. Установка зависимостей PHP
```bash
composer install --no-dev --optimize-autoloader
```
*(Скомпилированные стили и скрипты уже находятся в `public/build/`. Если вы хотите изменить дизайн, выполните `npm install && npm run build`).*

#### 3. Настройка файла окружения (`.env`)
```bash
cp .env.example .env
php artisan key:generate
```
При необходимости отредактируйте параметры в файле `.env`:
```ini
APP_NAME="SCUM Server Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://scum.yourdomain.com
```

#### 4. Инициализация локальной базы данных
Панель использует встроенную высокопроизводительную базу данных **SQLite в режиме WAL**:
```bash
# Создание файла базы данных
touch database/database.sqlite

# Применение структуры таблиц
php artisan migrate --force
```

#### 5. Создание администратора панели
```bash
php artisan panel:user
```
Введите желаемое имя пользователя (логин) и пароль администратора.

#### 6. Настройка прав доступа на папки (Linux)
Веб-сервер должен иметь права на запись в папки кэша, логов и базы данных:
```bash
sudo chown -R www-data:www-data storage database bootstrap/cache
sudo chmod -R 775 storage database bootstrap/cache
```

---

## 🗄 Как устроена база данных панели

В архитектуре SCUM Server Manager используются **две изолированные базы данных**:

```
scum-server-manager/
├── database/
│   └── database.sqlite      <-- 1. Локальная база данных панели (Laravel)
└── storage/
    └── app/
        └── scum/
            └── SCUM.db       <-- 2. Игровая база данных SCUM (синхронизируется с сервера)
```

1. **Локальная база веб-панели (`database/database.sqlite`)**:
   - Создается автоматически через команду `php artisan panel:install` или `touch database/database.sqlite`.
   - Хранит учетные записи, настройки серверов, цепочки автоматизации, аудит-логи и очереди RCON.
   - Для нее **не требуется настраивать сервер MySQL/MariaDB**. Для резервной копии достаточно просто скопировать файл `database.sqlite`.

2. **Игровая база сервера SCUM (`storage/app/scum/SCUM.db`)**:
   - **Создавать вручную не нужно!**
   - После первого входа в панель перейдите в раздел **«Серверы»** (`/servers`) и добавьте подключение (укажите IP, порт RCON и реквизиты FTP сервера SCUM).
   - Встроенный фоновый демон панели подключится к игровому серверу по FTP и автоматически выкачает свежий `SCUM.db`.
   - Демон синхронизации обновляет его в фоновом режиме по расписанию.

---

## 🖥 Настройка в Продакшн (Nginx & Systemd)

### Пример конфигурации Nginx (`/etc/nginx/sites-available/scum-panel.conf`):
```nginx
server {
    listen 80;
    server_name scum.yourdomain.com;
    root /var/www/scum-panel/public;

    index index.php index.html;
    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Фоновые службы Systemd (для демонов RCON и синхронизации)

Примеры готовых systemd-сервисов находятся в директории `deploy/`:

1. **RCON-демон** (`scum-rcon.service`):
   ```ini
   [Unit]
   Description=SCUM Server Manager RCON Worker
   After=network.target

   [Service]
   Type=simple
   User=www-data
   WorkingDirectory=/var/www/scum-panel
   ExecStart=/usr/bin/php /var/www/scum-panel/artisan scum:rcon --poll=150
   Restart=always
   RestartSec=5

   [Install]
   WantedBy=multi-user.target
   ```

2. **Демон синхронизации логов и базы** (`scum-watch.service`):
   ```ini
   [Service]
   ExecStart=/usr/bin/php /var/www/scum-panel/artisan scum:watch --poll=500
   ```

3. **Планировщик задач** (`scum-scheduler.service`):
   ```ini
   [Service]
   ExecStart=/usr/bin/php /var/www/scum-panel/artisan schedule:work
   ```

---

## ❓ Частые вопросы (FAQ & Troubleshooting)

#### 1. Ошибка «Database file does not exist: database.sqlite»
Выполните:
```bash
php artisan panel:install
# Либо вручную:
touch database/database.sqlite && php artisan migrate --force
```

#### 2. Ошибка «The stream or file ".../storage/logs/laravel.log" could not be opened: Permission denied»
Веб-сервер не имеет прав на запись в папки:
```bash
sudo chown -R www-data:www-data storage database bootstrap/cache
sudo chmod -R 775 storage database bootstrap/cache
```

#### 3. Как изменить пароль администратора или создать нового?
В любой момент в терминале выполните:
```bash
php artisan panel:user
```
И введите новый логин и пароль.

#### 4. Не подключается RCON к серверу SCUM
- Убедитесь, что порт RCON (по умолчанию `7004` UDP/TCP) открыт в брандмауэре хостинга.
- В файле конфигурации сервера SCUM проверьте, что опция RCON включена (`RconEnabled=1`) и пароль совпадает.

---

## 🧪 Тестирование и проверка качества

```bash
# Запуск полного набора из 72 автотестов:
php artisan test

# Статический анализ кода (PHPStan Level 6+):
vendor/bin/phpstan analyse --memory-limit=1G

# Форматирование кода по стандартам Laravel Pint:
vendor/bin/pint --test
```

---

## 📄 Лицензия

Проект распространяется под лицензией [MIT](LICENSE).
