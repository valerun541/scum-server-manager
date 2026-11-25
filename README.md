# SCUM Server Manager (SSM) v2.0

> **Веб-панель управления игровыми серверами SCUM** на базе Laravel 13, Alpine.js, Tailwind CSS и прямого асинхронного RCON/FTP шлюза для Linux-серверов.

---

## 🌟 Возможности панели

- **Интерактивная RCON-консоль (Interactive CLI)**:
  - Прямой защищённый шлюз выполнения команд в реальном времени через фоновый демон.
  - 238 официальных команд сервера SCUM: игроки, транспорт, спавн, базы, модерация, существа/роботы, экономика и события.
  - Быстрая шпаргалка синтаксиса и моментальная вставка команд в консоль в один клик.
- **Интеллектуальный каталог игровых объектов**:
  - Свыше 2 400 предметов, вся доступная техника, животные, роботы, NPC и ключевые точки интереса (POI).
  - Умный поиск с поддержкой сленга («калаш», «сишка», «дигл») и русской раскладки.
  - Оптимизированные WebP-иконки для каждого предмета и транспортного средства.
- **Интерактивная карта острова SCUM**:
  - Отображение расположения игроков онлайн, транспорта и баз в реальном времени.
  - Телепортация и навигация по мировым координатам.
- **База данных SCUM (SCUM.db & Telemetry)**:
  - Глубокая аналитика игроков: досье, инвентарь, баланс валюты (Cash/Gold), очки славы, время в игре.
  - Инспектор баз и флагов с координатами и элементами построек.
  - Инспектор транспорта с отслеживанием владельцев и локаций.
- **Автоматизация и триггеры**:
  - Визуальный конструктор сценариев и расписаний (Schedules).
  - Интеграция с магазином WARGM (автовыдача доната в игре).
  - Airdrop Spawner, система вознаграждений и плагин страхования техники.
- **Безопасность**:
  - Двухфакторная аутентификация (2FA / TOTP с QR-кодом).
  - Защита IP Whitelist с поддержкой CIDR-подсетей.
  - Защита от перебора паролей (Brute-force protection).
  - Журнал аудита действий администраторов (Audit Logs).

---

## 📋 Системные требования

Панель разработана для развертывания на Linux-серверах (VDS / VPS / Dedicated).

| Компонент | Рекомендуемая версия | Примечание |
| :--- | :--- | :--- |
| **Операционная система** | **Ubuntu 24.04 LTS** или **Ubuntu 22.04 LTS** (также поддерживается Debian 11/12) | 64-bit Linux |
| **Веб-сервер** | **Nginx** | Обрабатывает статику и проксирует PHP-FPM |
| **PHP** | **8.3** (или 8.2 / 8.4) | `php-fpm` и `php-cli` |
| **Расширения PHP** | `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`, `openssl`, `bcmath`, `fileinfo`, `zip`, `xml` | **MySQL/PostgreSQL не требуются!** База панели работает на встроенном SQLite |
| **Пакетный менеджер** | **Composer 2.x** | Менеджер зависимостей PHP |
| **Минимальные ресурсы** | 1 CPU, 1 ГБ RAM, 10 ГБ SSD | Для панели и синхронизации логов |

---

## 🚀 Пошаговая установка на Linux от начала и до конца

Ниже приведено полное руководство по установке на чистый сервер с **Ubuntu 22.04 / 24.04 LTS**.

### Шаг 1. Подготовка сервера и установка пакетов

Подключитесь к серверу по SSH под пользователем `root` или с правами `sudo`:

```bash
# 1.1 Обновите список пакетов и систему
sudo apt update && sudo apt upgrade -y

# 1.2 Установите вспомогательные утилиты
sudo apt install -y software-properties-common curl git unzip ufw

# 1.3 Добавьте официальный репозиторий PHP (PPA Ondřej Surý)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# 1.4 Установите Nginx, PHP 8.3 и все необходимые модули
sudo apt install -y nginx \
    php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-curl \
    php8.3-mbstring php8.3-bcmath php8.3-zip php8.3-xml

# 1.5 Установите Composer глобально
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

Проверьте корректность версий:
```bash
php -v
composer -V
nginx -v
```

---

### Шаг 2. Клонирование репозитория

Склонируйте проект в стандартную директорию веб-сервера (например, `/var/www/scum-panel`):

```bash
sudo git clone https://github.com/your-username/scum-server-manager.git /var/www/scum-panel
cd /var/www/scum-panel
```

---

### Шаг 3. Установка PHP-зависимостей

Установите оптимизированные зависимости приложения без dev-пакетов:

```bash
composer install --no-dev --optimize-autoloader
```
*(Скомпилированные фронтенд-стили и JavaScript уже включены в репозиторий в папке `public/build/`, поэтому установка Node.js на сервере не требуется).*

---

### Шаг 4. Автоматическая инициализация панели (Мастер установки)

Запустите встроенный мастер настройки:

```bash
php artisan panel:install
```

**Что мастер сделает автоматически:**
1. Создаст файл конфигурации `.env` из шаблона `.env.example`.
2. Сгенерирует безопасный ключ приложения `APP_KEY`.
3. Создаст файл локальной базы данных `database/database.sqlite`.
4. Применит все миграции таблиц (`php artisan migrate --force`).
5. Интерактивно запросит **логин и пароль первого администратора**.

---

### Шаг 5. Настройка прав доступа на директории

Веб-сервер `www-data` должен иметь полные права на запись в папки логов, сессий, кэша и базы данных:

```bash
# Передаем владение веб-серверу Nginx / PHP-FPM
sudo chown -R www-data:www-data /var/www/scum-panel/storage \
                                /var/www/scum-panel/database \
                                /var/www/scum-panel/bootstrap/cache

# Устанавливаем корректные права на чтение и запись
sudo chmod -R 775 /var/www/scum-panel/storage \
                  /var/www/scum-panel/database \
                  /var/www/scum-panel/bootstrap/cache
```

---

### Шаг 6. Настройка веб-сервера Nginx

Создайте файл конфигурации виртуального хоста:

```bash
sudo nano /etc/nginx/sites-available/scum-panel.conf
```

Вставьте следующую конфигурацию (замените `panel.yourdomain.com` на ваш домен или IP-адрес):

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

    # Обработка PHP через сокет php8.3-fpm
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Запрет доступа к скрытым файлам (.env, .git и т.д.)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Активируйте сайт и перезапустите Nginx:

```bash
# Создаем символическую ссылку
sudo ln -s /etc/nginx/sites-available/scum-panel.conf /etc/nginx/sites-enabled/

# Проверяем синтаксис конфигурации Nginx
sudo nginx -t

# Применяем изменения
sudo systemctl reload nginx
```

#### Настройка бесплатного SSL-сертификата (HTTPS через Let's Encrypt):
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d panel.yourdomain.com
```

---

### Шаг 7. Настройка фоновых демонов Systemd

Панель использует фоновые службы для непрерывного RCON-взаимодействия и синхронизации логов/базы без блокировки веб-интерфейса.

#### 1. Демон RCON (`scum-rcon.service`)
Создайте файл службы:
```bash
sudo nano /etc/systemd/system/scum-rcon.service
```
Содержимое:
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

#### 2. Демон синхронизации логов и базы (`scum-watch.service`)
```bash
sudo nano /etc/systemd/system/scum-watch.service
```
Содержимое:
```ini
[Unit]
Description=SCUM Server Manager Log & DB Watcher
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/scum-panel
ExecStart=/usr/bin/php /var/www/scum-panel/artisan scum:watch --poll=500
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

#### 3. Планировщик периодических задач (`scum-scheduler.service`)
```bash
sudo nano /etc/systemd/system/scum-scheduler.service
```
Содержимое:
```ini
[Unit]
Description=SCUM Server Manager Scheduler
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/scum-panel
ExecStart=/usr/bin/php /var/www/scum-panel/artisan schedule:work
Restart=always
RestartSec=15

[Install]
WantedBy=multi-user.target
```

#### Активация и запуск всех служб:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now scum-rcon.service scum-watch.service scum-scheduler.service
```

Проверка статуса служб:
```bash
sudo systemctl status scum-rcon.service
sudo systemctl status scum-watch.service
sudo systemctl status scum-scheduler.service
```

---

## 🗄 Как устроена база данных в панели

В архитектуре используются **две изолированные базы данных**:

```
/var/www/scum-panel/
├── database/
│   └── database.sqlite      <-- 1. Локальная база панели (Laravel)
└── storage/
    └── app/
        └── scum/
            └── SCUM.db       <-- 2. Игровая база данных SCUM (скачивается с сервера игры)
```

1. **Локальная база веб-панели (`database/database.sqlite`)**:
   - Автоматически создается при выполнении команды `php artisan panel:install`.
   - Хранит аккаунты администраторов, настройки подключенных серверов, правила автоматизации, аудит-логи и RCON-очередь.
   - Работает через встроенный движок **SQLite в режиме WAL** (высокая производительность и отсутствие необходимости администрировать MySQL).
   - **Резервное копирование:** Чтобы сохранить бэкап всей панели, достаточно скопировать один файл `database.sqlite` и файл `.env`.

2. **Игровая база сервера SCUM (`storage/app/scum/SCUM.db`)**:
   - **Создавать вручную не требуется!**
   - Перейдите в веб-интерфейсе в раздел **«Серверы»** (`/servers`) и укажите IP, RCON-порт и реквизиты FTP вашего сервера SCUM.
   - Демон панели подключится к серверу игры и автоматически скачает актуальный `SCUM.db`.
   - Служба `scum-watch` будет регулярно синхронизировать изменения в фоновом режиме.

---

## 🔄 Обновление панели

Когда выходят обновления в репозитории:

```bash
cd /var/www/scum-panel

# 1. Загрузка обновленного кода
sudo git pull origin main

# 2. Обновление зависимостей
composer install --no-dev --optimize-autoloader

# 3. Применение новых миграций базы данных
php artisan migrate --force

# 4. Сброс и оптимизация кэша
php artisan optimize

# 5. Перезапуск фоновых демонов
sudo systemctl restart scum-rcon.service scum-watch.service scum-scheduler.service
```

---

## ❓ Диагностика и решение проблем (Troubleshooting)

#### 1. Ошибка прав доступа: «Permission denied» в storage или database
Выполните повторно установку прав:
```bash
sudo chown -R www-data:www-data /var/www/scum-panel/storage /var/www/scum-panel/database /var/www/scum-panel/bootstrap/cache
sudo chmod -R 775 /var/www/scum-panel/storage /var/www/scum-panel/database /var/www/scum-panel/bootstrap/cache
```

#### 2. Как сбросить пароль администратора панели?
Если вы забыли пароль, выполните в терминале:
```bash
cd /var/www/scum-panel
php artisan panel:user
```
Команда запросит логин и новый пароль.

#### 3. Как просмотреть живой лог службы RCON?
```bash
journalctl -u scum-rcon.service -f
```

#### 4. Не проходит RCON-подключение к игровому серверу
- Проверьте, открыт ли исходящий порт RCON (по умолчанию `7004` UDP/TCP) на вашем VDS:
  ```bash
  nc -zv <IP_СЕРВЕРА_SCUM> 7004
  ```
- В настройках сервера SCUM убедитесь, что параметр `RconEnabled=1` и пароль совпадает.

---

## 🧪 Тестирование и контроль качества

```bash
# Запуск 72 автоматических тестов
php artisan test

# Анализ кода с PHPStan (Level 5)
vendor/bin/phpstan analyse --memory-limit=1G

# Проверка стандартов кода Laravel Pint
vendor/bin/pint --test
```

---

## 📄 Лицензия

Проект распространяется под открытой лицензией [MIT](LICENSE).
