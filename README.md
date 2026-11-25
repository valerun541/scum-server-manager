# SCUM Server Manager (SSM) v2.0

> **Современная веб-панель управления игровыми серверами SCUM** на базе **Laravel 13**, **PHP 8.4**, **Alpine.js** и **Tailwind CSS 4** с асинхронным RCON/FTP-шлюзом для Linux-серверов.

---

## 💡 Идея и обязательный модуль для игрового сервера

* **Источник идеи:** проект вдохновлён и создан на основе идей из репозитория [AMURKA-PVE-SCUM/scum-server-manager](https://github.com/AMURKA-PVE-SCUM/scum-server-manager).
* **Обязательное требование для игрового сервера SCUM:** для работы RCON-консоли, выдачи предметов, спавна техники, чат-команд и всех плагинов на стороне вашего игрового сервера SCUM **должен быть установлен модуль** 👉 [AMURKA-PVE-SCUM/scum-rcon](https://github.com/AMURKA-PVE-SCUM/scum-rcon).

---

## 🌟 Подробные возможности панели

### 1. Мультисерверный шлюз и мониторинг
- **Центральный шлюз серверов (`/servers`):** добавление, редактирование, удаление и быстрое переключение между несколькими игровыми серверами SCUM.
- **Живая диагностика подключений:** проверка статуса RCON-сокета и FTP/SFTP-доступа прямо из карточки сервера.
- **Мониторинг хоста и демонов:** отслеживание активности фоновых служб (`scum:rcon`, `scum:watch`, `schedule:work`) и ресурсов сервера.

### 2. Управление игроками онлайн (`/players`)
- **Список игроков в реальном времени:** никнейм, SteamID, пинг, статус и быстрые действия в один клик.
- **Моментальное администрирование:** кик, бан/разбан, нокаут, мут текстового чата и голоса, выдача валюты (Cash / Gold) и очков славы (Fame), телепортация (по координатам, к игроку, к транспорту), `Unstuck`, личные уведомления и сообщения в чат.
- **Досье Whois:** просмотр детальной информации об игроке без входа в игру.

### 3. Интерактивная карта острова SCUM (`/map`)
- **Тактическая карта в реальном времени:** отображение онлайн-игроков, созданного транспорта, баз (флагов) и пользовательских зон.
- **Инспектор содержимого:** просмотр координат, владельцев техники и содержимого сундуков прямо с карты.
- **Быстрый телепорт:** копирование координат и быстрый переход к досье игрока.

### 4. Интерактивная RCON-консоль и Спавнер (`/rcon`)
- **Web-терминал CLI:** выполнение любых RCON-команд через защищённую очередь демона `scum:rcon` с отображением истории и полных ответов сервера.
- **238 официальных команд SCUM:** каталог быстрых команд, разбитый по 10 категориям (Сервер, Игроки, Транспорт, Базы, Экономика, Модерация, Существа/Роботы, Спавн, Сквады, Квесты), и встроенная шпаргалка параметров.
- **Умный каталог спавна (`<x-catalog-picker>`):** более **2 400** предметов, техника, NPC/животные и POI-метки острова с WebP-иконками, фильтрацией по категориям и поиском по русскому сленгу («калаш», «дигл», «лайка» и т.д.).

### 5. База данных `SCUM.db` и Телеметрия (`/scumdb`)
- **Автоматическая синхронизация по FTP/SFTP:** панель сама скачивает `SCUM.db` с игрового сервера и поддерживает локальную копию в актуальном состоянии.
- **Профили персонажей (`/scumdb/player/{steamId}`):** инвентарь, быстрые слоты, кошелёк/банк, атрибуты (Сила, Выносливость, Ловкость, Интеллект) и прокачка всех игровых навыков.
- **Разделы телеметрии:** отряды (Squads), весь заспавненный транспорт с владельцами, флаги и элементы баз, банковские счета, таблица лидеров экономики.
- **Универсальный редактор таблиц SQLite:** безопасный просмотр, поиск, редактирование, добавление и удаление строк любой таблицы `SCUM.db` с защитой от перезаписи локальных правок до выгрузки на сервер.

### 6. Редактор конфигов сервера (`/config-files`)
- **Прямое редактирование по FTP/SFTP:** работа с `ServerSettings.ini`, `EconomyOverride.json`, `RaidSettings.json` и другими файлами конфигурации прямо в браузере (поддержка UTF-8 и UTF-16LE).
- **Локальные бэкапы конфигов:** создание резервных копий перед сохранением и откат к любой версии в один клик.

### 7. Паки лута и Наборы (`/packs`)
- **Конструктор наборов (Starter, VIP, Event и др.):** настройка состава предметов, транспорта, валюты, кулдаунов (таймеров повторного получения) и прав доступа.
- **Выдача через панель и чат-команды:** мгновенная выдача игроку из панели или автоматическая выдача по триггеру в игровом чате.

### 8. Визуальный конструктор автоматизации (`/automation`)
- **Триггеры:** чат-команды (с алиасами и кулдауном), игровые события (`player_join` и др.) и расписания по времени (`schedule`).
- **Цепочки действий:** RCON-команды, уведомления в **Lolka** через Webhook, выдача покупок WARGM, задержки, условия (`if/else`), переменные, HTTP-запросы (с SSRF-защитой) и запись в лог.
- **Готовые шаблоны (1-Click) и Экспертный JSON-режим.**

### 9. Интеграция с магазином WARGM (`/wargm`)
- **Автовыдача доната:** привязка товаров WARGM к карточкам предметов, наборов, транспорта или валюты в панели.
- **История доставок, тест API и экспорт/импорт карточек** в формате JSON.

### 10. Встроенные игровые плагины (`/plugins/*`)
- **Телепорт:** настраиваемые точки телепортации по чат-командам.
- **VIP-система:** управление VIP-игроками и сроками действия привилегий.
- **SaveHome:** сохранение и возврат в точку дома по команде в чате.
- **Airdrop-система:** вызов груза по расписанию или вручную, калибровка точек сброса.
- **Страхование транспорта:** оформление страховых полисов на технику игроков и обработка заявок на восстановление.
- **Награды за онлайн:** почасовые награды, награды для топ-игроков и чёрный список.

### 11. Живые логи и Безопасность
- **Мониторинг всех логов (`/logs/*`):** игровой чат (с возможностью отправки сообщений с сервера), действия админов, входы/выходы, убийства, транспорт, сундуки, экономика, очки славы, нарушения, квесты, RCON-команды и аудит панели (включая SSE-стриминг в реальном времени).
- **Безопасность:** двухфакторная аутентификация (2FA / TOTP с QR-кодом), белый список IP-адресов с поддержкой CIDR-подсетей, защита от подбора паролей, управление активными сессиями.
- **Внешнее API (`/api/v1/*`):** REST-эндпоинты для интеграции с сайтами и ботами (авторизация по `X-API-Key` / `Bearer` токену, health-check `/api/health`).

---

## 📋 Системные требования (Linux)

Панель рассчитана на развёртывание на **Linux-сервере** (VPS / VDS / Dedicated).

| Компонент | Требование (актуально на 2026) | Примечание |
| :--- | :--- | :--- |
| **Операционная система** | **Ubuntu 24.04 LTS** / **22.04 LTS** или **Debian 12** | 64-bit Linux |
| **PHP** | **8.3** или **8.4** (`php-fpm` + `php-cli`) | В `composer.json` требуется PHP `^8.3` |
| **Расширения PHP** | `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`, `openssl`, `bcmath`, `fileinfo`, `zip`, `xml`, `ctype`, `tokenizer` | База панели работает на **SQLite 3** (MySQL/PostgreSQL не нужны) |
| **Веб-сервер** | **Nginx** (1.24+) | Проксирование PHP-FPM и отдача статики |
| **Менеджер процессов** | **Supervisor** или **Systemd** | Для постоянной работы 3 фоновых демонов |
| **Composer** | **2.7+** | Установка PHP-зависимостей |
| **Node.js** *(опционально)* | **20 LTS / 22 LTS** | Нужен **только** если вы редактируете CSS/JS. Готовые собранные ассеты уже лежат в `public/build/` |
| **Игровой сервер SCUM** | Установленный **[scum-rcon](https://github.com/AMURKA-PVE-SCUM/scum-rcon)** + доступ по FTP/SFTP | Обязательно для работы команд и синхронизации `SCUM.db` |
| **Ресурсы VPS** | От **1–2 vCPU**, **1–2 ГБ RAM**, **10 ГБ SSD** | Рекомендуется 2 ГБ RAM для комфортной работы демонов |

---

## 🚀 Полная пошаговая установка на Linux (Ubuntu 24.04 / Debian 12)

### Шаг 1. Подготовка системы и установка пакетов

Подключитесь к серверу по SSH под `root` или пользователем с правами `sudo`:

```bash
# 1.1 Обновляем систему
sudo apt update && sudo apt upgrade -y

# 1.2 Ставим базовые утилиты, Nginx, Supervisor и SQLite CLI
sudo apt install -y software-properties-common curl git unzip ufw nginx supervisor sqlite3

# 1.3 Добавляем свежий репозиторий PHP (для Ubuntu)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# 1.4 Устанавливаем PHP 8.4 (или 8.3) и все нужные расширения
sudo apt install -y php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-curl \
    php8.4-mbstring php8.4-bcmath php8.4-zip php8.4-xml php8.4-intl

# 1.5 Устанавливаем Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Проверка версий
php -v && composer -V && nginx -v && supervisord -v
```
*(На Debian 12 вместо `add-apt-repository ppa:ondrej/php` подключите репозиторий `packages.sury.org/php` или используйте системный PHP `8.2+`, если обновили ограничение, но рекомендуется PHP 8.3/8.4).*

---

### Шаг 2. Клонирование проекта

```bash
sudo mkdir -p /var/www/scum-panel
sudo chown -R $USER:$USER /var/www/scum-panel

git clone https://github.com/<ВАШ_АККАУНТ>/scum-server-manager.git /var/www/scum-panel
cd /var/www/scum-panel
```

---

### Шаг 3. Установка PHP-зависимостей

```bash
composer install --no-dev --optimize-autoloader
```
*(Все стили и скрипты уже скомпилированы в `public/build/`, поэтому `npm install` на боевом сервере не требуется).*

---

### Шаг 4. Автоматическая инициализация (База данных и Администратор)

Запустите встроенный мастер установки Laravel:

```bash
php artisan panel:install
```

**Что сделает мастер автоматически:**
1. Скопирует `.env.example` в `.env` (если `.env` отсутствует).
2. Сгенерирует уникальный ключ шифрования `APP_KEY`.
3. Создаст файл локальной базы данных `database/database.sqlite`.
4. Развернёт все таблицы миграциями (`php artisan migrate --force`).
5. Интерактивно запросит **логин** и **пароль** первого администратора панели.

> После установки откройте `.env` (`nano .env`) и для продакшена проверьте основные параметры:
> ```ini
> APP_ENV=production
> APP_DEBUG=false
> APP_URL=https://panel.yourdomain.com
> ```
> Затем выполните кэширование конфигурации:
> ```bash
> php artisan optimize
> ```

---

### Шаг 5. Настройка прав доступа на директории

Пользователь веб-сервера `www-data` (под ним работают Nginx, PHP-FPM и все демоны) должен владеть папками записи:

```bash
sudo chown -R www-data:www-data /var/www/scum-panel
sudo chmod -R 775 /var/www/scum-panel/storage \
                  /var/www/scum-panel/database \
                  /var/www/scum-panel/bootstrap/cache
```

---

### Шаг 6. Настройка Nginx и SSL (HTTPS)

Создайте конфигурацию виртуального хоста:

```bash
sudo nano /etc/nginx/sites-available/scum-panel.conf
```

Вставьте конфигурацию (замените `panel.yourdomain.com` на ваш домен или IP-адрес сервера):

```nginx
server {
    listen 80;
    server_name panel.yourdomain.com;
    root /var/www/scum-panel/public;

    index index.php index.html;
    charset utf-8;
    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
*(Если установили PHP 8.3, укажите `unix:/run/php/php8.3-fpm.sock`).*

Активируйте сайт и проверьте Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/scum-panel.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

#### Подключение бесплатного HTTPS (Certbot):
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d panel.yourdomain.com
```

---

### Шаг 7. Настройка и запуск фоновых демонов (Supervisor)

Для полноценной работы панели в фоне должны постоянно работать **3 процесса**:

1. **`scum-rcon`** (`artisan scum:rcon --poll=150`) — единственный владелец RCON-сокета; выполняет команды веб-консоли, чат-команды, выдачу наборов и тики плагинов.
2. **`scum-watch`** (`artisan scum:watch --poll=500`) — мониторит игровые логи по FTP/SFTP, ставит события в очередь, синхронизирует `SCUM.db` и подогревает кэш карты.
3. **`scum-scheduler`** (`artisan schedule:work`) — планировщик: каждую минуту запускает `scum:dispatch-tick` (расписания и плагины) и каждые 15 минут `scum:maintenance` (очистка старых задач и кэша).

В папке `deploy/supervisor/` уже лежат готовые конфиги. Скопируйте их, обновите путь на `/var/www/scum-panel` и запустите:

```bash
# Копируем готовые конфиги Supervisor
sudo cp /var/www/scum-panel/deploy/supervisor/*.conf /etc/supervisor/conf.d/

# Заменяем дефолтный путь в конфигах на /var/www/scum-panel
sudo sed -i 's|/var/www/scum-manager-laravel|/var/www/scum-panel|g' /etc/supervisor/conf.d/scum-*.conf

# Перечитываем конфигурацию и запускаем все 3 демона
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start scum-rcon scum-watch scum-scheduler

# Проверяем, что все 3 процесса в статусе RUNNING
sudo supervisorctl status
```

<details>
<summary>Альтернатива: запуск через Systemd вместо Supervisor</summary>

Если вы предпочитаете чистый `systemd`, создайте 3 юнита в `/etc/systemd/system/`:

1. `/etc/systemd/system/scum-rcon.service`:
```ini
[Unit]
Description=SCUM Server Manager RCON Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/scum-panel
ExecStart=/usr/bin/php /var/www/scum-panel/artisan scum:rcon --poll=150
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

2. `/etc/systemd/system/scum-watch.service` (аналогично, но `ExecStart=/usr/bin/php /var/www/scum-panel/artisan scum:watch --poll=500`).
3. `/etc/systemd/system/scum-scheduler.service` (аналогично, но `ExecStart=/usr/bin/php /var/www/scum-panel/artisan schedule:work`).

Запуск:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now scum-rcon scum-watch scum-scheduler
```
</details>

---

### Шаг 8. Первое подключение игрового сервера SCUM

1. Убедитесь, что на вашем игровом сервере SCUM установлен и настроен **[AMURKA-PVE-SCUM/scum-rcon](https://github.com/AMURKA-PVE-SCUM/scum-rcon)**.
2. Откройте панель в браузере (`https://panel.yourdomain.com`) и войдите под логином и паролем, заданными на Шаге 4.
3. Нажмите **«Добавить сервер»**, заполните:
   - **RCON:** IP, порт (обычно `7004`) и пароль из настроек `scum-rcon`.
   - **FTP / SFTP:** хост, порт (`21` для FTP или `22` для SFTP), логин, пароль и путь к папке сохранения/логов SCUM.
4. Нажмите **«Проверить RCON»** и **«Проверить FTP»**, сохраните сервер и нажмите **«Войти в панель управления»**.

---

## 🗄 Архитектура баз данных и Бэкапы

```
/var/www/scum-panel/
├── database/
│   └── database.sqlite      <-- База панели (настройки, серверы, воркфлоу, аудит, очереди)
└── storage/
    └── app/scum/
        └── SCUM.db          <-- Игровая база SCUM (автоматически скачивается демоном по FTP)
```

- **`database/database.sqlite`** создаётся автоматически при `php artisan panel:install` и работает в режиме WAL.
- **`storage/app/scum/SCUM.db`** создавать руками не нужно — демон `scum-watch` сам скачает её с игрового сервера.
- **Автоматический бэкап:** используйте готовый скрипт [`deploy/backup.sh`](deploy/backup.sh), который безопасно делает снимок SQLite (`.backup`), пакует `.env` и `config/` в `/var/backups/scum-manager/` и хранит архивы 7 дней:
  ```bash
  sudo bash /var/www/scum-panel/deploy/backup.sh
  ```

---

## 🔄 Обновление панели

```bash
cd /var/www/scum-panel
sudo -u www-data git pull origin main
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
sudo supervisorctl restart scum-rcon scum-watch scum-scheduler
```

---

## ❓ Частые вопросы и диагностика (Troubleshooting)

1. **Просмотр логов демонов в реальном времени:**
   ```bash
   tail -f /var/www/scum-panel/storage/logs/supervisor-*.log
   tail -f /var/www/scum-panel/storage/logs/laravel.log
   ```
2. **Ошибка записи в SQLite или логи (`Permission denied`):**
   ```bash
   sudo chown -R www-data:www-data /var/www/scum-panel/storage /var/www/scum-panel/database /var/www/scum-panel/bootstrap/cache
   sudo chmod -R 775 /var/www/scum-panel/storage /var/www/scum-panel/database /var/www/scum-panel/bootstrap/cache
   ```
3. **Сбросить пароль администратора или создать нового:**
   ```bash
   cd /var/www/scum-panel
   sudo -u www-data php artisan panel:user
   ```
4. **Не проходит RCON-подключение:**
   - Проверьте, что на игровом сервере установлен [AMURKA-PVE-SCUM/scum-rcon](https://github.com/AMURKA-PVE-SCUM/scum-rcon) и открыт RCON-порт в фаерволе:
     ```bash
     nc -zv <IP_ИГРОВОГО_СЕРВЕРА> 7004
     ```

---

## 🧪 Тестирование и контроль качества

```bash
php artisan test
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

---

## 📄 Лицензия

Проект распространяется под лицензией [MIT](LICENSE).
