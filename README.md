<div align="center">

# SCUM Server Manager (SSM)

**Веб-панель управления игровыми серверами SCUM на базе Laravel 13, PHP 8.4, Alpine.js и Tailwind CSS 4**

[![PHP](https://img.shields.io/badge/PHP-8.3%20%7C%208.4-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![SQLite](https://img.shields.io/badge/Database-SQLite%20WAL-003B57?style=flat-square&logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![Platform](https://img.shields.io/badge/Platform-Linux%20(Ubuntu%20%7C%20Debian)-FCC624?style=flat-square&logo=linux&logoColor=black)](#системные-требования)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)
[![Issues](https://img.shields.io/github/issues/valerun541/scum-server-manager?style=flat-square)](https://github.com/valerun541/scum-server-manager/issues)

</div>

---

## 📑 Содержание

- [О проекте и обязательные требования](#-о-проекте-и-обязательные-требования)
- [Возможности панели](#-возможности-панели)
- [Системные требования](#-системные-требования)
- [Пошаговая установка на Linux](#-пошаговая-установка-на-linux)
- [Настройка фоновых демонов](#-настройка-фоновых-демонов)
- [Подключение игрового сервера](#-подключение-игрового-сервера)
- [Архитектура БД и резервное копирование](#-архитектура-бд-и-резервное-копирование)
- [Обновление и диагностика](#-обновление-и-диагностика)
- [Тестирование и качество кода](#-тестирование-и-качество-кода)
- [Поддержка и обратная связь](#-поддержка-и-обратная-связь)
- [Лицензия](#-лицензия)

---

## 💡 О проекте и обязательные требования

* **Источник идеи:** проект вдохновлён и создан на основе идей из репозитория [AMURKA-PVE-SCUM/scum-server-manager](https://github.com/AMURKA-PVE-SCUM/scum-server-manager).
* **Обязательный модуль на игровом сервере:** для работы RCON-консоли, выдачи предметов, спавна техники, чат-команд и всех плагинов на стороне вашего сервера SCUM **должен быть установлен модуль** [AMURKA-PVE-SCUM/scum-rcon](https://github.com/AMURKA-PVE-SCUM/scum-rcon).

---

## 🌟 Возможности панели

| Модуль | Описание |
| :--- | :--- |
| **Мультисерверный шлюз** (`/servers`) | Добавление, редактирование, удаление и быстрое переключение между серверами SCUM. Живая проверка RCON-сокета, FTP/SFTP-доступа и статуса фоновых демонов. |
| **Игроки онлайн** (`/players`) | Мониторинг игроков в реальном времени, досье `Whois`, кик, бан/разбан, нокаут, мут чата и голоса, выдача валюты (`Cash`/`Gold`) и `Fame`, телепортация и `Unstuck`. |
| **Интерактивная карта** (`/map`) | Отображение игроков, транспорта, баз (флагов) и кастомных зон в реальном времени, просмотр содержимого сундуков и копирование координат. |
| **RCON-консоль и Спавнер** (`/rcon`) | Web-терминал с очередью выполнения, историей и 238 официальными командами SCUM в 10 категориях. Встроенный каталог (>2 400 предметов, транспорт, NPC, POI) с WebP-иконками и поиском по русскому сленгу. |
| **База данных `SCUM.db`** (`/scumdb`) | Автосинхронизация по FTP/SFTP, профили игроков (инвентарь, быстрые слоты, кошелёк, атрибуты, навыки), отряды, транспорт, флаги, банки, экономика и универсальный редактор таблиц SQLite. |
| **Конфиги сервера** (`/config-files`) | Редактирование `ServerSettings.ini`, `EconomyOverride.json`, `RaidSettings.json` и др. по FTP/SFTP (UTF-8 / UTF-16LE) с локальными бэкапами и откатом в 1 клик. |
| **Паки лута** (`/packs`) | Конструктор наборов (Starter, VIP, Event) с кулдаунами, правами доступа и выдачей из панели или по команде в чате. |
| **Автоматизация** (`/automation`) | Визуальный конструктор сценариев: чат-триггеры, события (`player_join`), расписания (`schedule`), RCON-действия, уведомления в **Lolka** (Webhook), WARGM, условия, переменные, HTTP-запросы (с SSRF-защитой) и JSON-режим. |
| **Магазин WARGM** (`/wargm`) | Автоматическая выдача покупок в игре, привязка карточек к предметам/наборам/транспорту, история доставок, экспорт/импорт JSON. |
| **Игровые плагины** (`/plugins/*`) | Телепорт, VIP-система, SaveHome, Airdrop (по расписанию и с калибровкой точек), Страхование транспорта, Награды за онлайн. |
| **Логи и Безопасность** (`/logs/*`, `/account`) | Живые логи (чат, админ, входы, убийства, экономика, квесты, RCON, аудит, SSE-стриминг), 2FA (TOTP), IP Whitelist (CIDR), защита от перебора паролей и REST API (`/api/v1/*`). |

---

## 📋 Системные требования

Панель рассчитана на развёртывание на **Linux-сервере** (VPS / VDS / Dedicated).

| Компонент | Требование | Примечание |
| :--- | :--- | :--- |
| **ОС** | **Ubuntu 24.04 LTS** / **22.04 LTS** или **Debian 12** | 64-bit Linux |
| **PHP** | **8.3** или **8.4** (`php-fpm` + `php-cli`) | В `composer.json` задано `^8.3` |
| **Расширения PHP** | `pdo_sqlite`, `sqlite3`, `curl`, `mbstring`, `openssl`, `bcmath`, `fileinfo`, `zip`, `xml`, `ctype`, `tokenizer` | База панели работает на **SQLite 3** (MySQL/PostgreSQL не требуются) |
| **Веб-сервер** | **Nginx** `1.24+` | Проксирование PHP-FPM и отдача статики |
| **Менеджер процессов** | **Supervisor** или **Systemd** | Для постоянной работы 3 фоновых демонов |
| **Composer** | **2.7+** | Установка PHP-зависимостей |
| **Node.js** *(опционально)* | **20 LTS / 22 LTS** | Нужен только при изменении исходного CSS/JS. Готовые ассеты уже включены в `public/build/` |
| **Сервер SCUM** | Установленный **[scum-rcon](https://github.com/AMURKA-PVE-SCUM/scum-rcon)** + доступ по FTP/SFTP | Обязательно для команд и синхронизации `SCUM.db` |
| **Ресурсы VPS** | От **1–2 vCPU**, **1–2 ГБ RAM**, **10 ГБ SSD** | Рекомендуется 2 ГБ RAM |

---

## 🚀 Пошаговая установка на Linux

### 1. Подготовка системы и установка пакетов (Ubuntu 24.04 / 22.04)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip ufw nginx supervisor sqlite3

# Репозиторий свежих версий PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# PHP 8.4 (или замените 8.4 на 8.3) и необходимые модули
sudo apt install -y php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-curl \
    php8.4-mbstring php8.4-bcmath php8.4-zip php8.4-xml php8.4-intl

# Установка Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

### 2. Клонирование и установка зависимостей

```bash
sudo mkdir -p /var/www/scum-panel
sudo chown -R $USER:$USER /var/www/scum-panel

git clone https://github.com/valerun541/scum-server-manager.git /var/www/scum-panel
cd /var/www/scum-panel

composer install --no-dev --optimize-autoloader
```

### 3. Автоматическая инициализация панели

Запустите встроенный мастер установки:

```bash
php artisan panel:install
```

Мастер автоматически:
1. Создаст `.env` из `.env.example`.
2. Сгенерирует ключ шифрования `APP_KEY`.
3. Создаст файл базы данных `database/database.sqlite`.
4. Применит все миграции таблиц (`php artisan migrate --force`).
5. Запросит логин и пароль первого администратора.

Затем настройте параметры окружения в `.env` для продакшена:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.yourdomain.com
```

И закэшируйте конфигурацию:

```bash
php artisan optimize
```

### 4. Права доступа

```bash
sudo chown -R www-data:www-data /var/www/scum-panel
sudo chmod -R 775 /var/www/scum-panel/storage \
                  /var/www/scum-panel/database \
                  /var/www/scum-panel/bootstrap/cache
```

### 5. Настройка Nginx и HTTPS

Создайте файл `/etc/nginx/sites-available/scum-panel.conf`:

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

Активируйте сайт и выпустите SSL-сертификат:

```bash
sudo ln -s /etc/nginx/sites-available/scum-panel.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# Бесплатный HTTPS (Let's Encrypt)
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d panel.yourdomain.com
```

---

## ⚙️ Настройка фоновых демонов

Для работы очередей RCON, логов, синхронизации `SCUM.db` и расписаний должны постоянно работать **3 службы**:

| Процесс | Команда | Назначение |
| :--- | :--- | :--- |
| **`scum-rcon`** | `php artisan scum:rcon --poll=150` | Единственный владелец RCON-сокета; выполняет команды консоли, чат-команды и тики плагинов. |
| **`scum-watch`** | `php artisan scum:watch --poll=500` | Мониторинг логов по FTP/SFTP, очередь событий, синхронизация `SCUM.db` и кэш карты. |
| **`scum-scheduler`** | `php artisan schedule:work` | Планировщик: `scum:dispatch-tick` (раз в минуту) и `scum:maintenance` (раз в 15 минут). |

### Запуск через Supervisor (рекомендуется)

```bash
sudo cp /var/www/scum-panel/deploy/supervisor/*.conf /etc/supervisor/conf.d/
sudo sed -i 's|/var/www/scum-manager-laravel|/var/www/scum-panel|g' /etc/supervisor/conf.d/scum-*.conf

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start scum-rcon scum-watch scum-scheduler
sudo supervisorctl status
```

<details>
<summary>Альтернатива: запуск через Systemd</summary>

Создайте три юнита в `/etc/systemd/system/` (`scum-rcon.service`, `scum-watch.service`, `scum-scheduler.service`):

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
*(Для `scum-watch.service` укажите `scum:watch --poll=500`, для `scum-scheduler.service` — `schedule:work`).*

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now scum-rcon scum-watch scum-scheduler
```

</details>

---

## 🎮 Подключение игрового сервера

1. Убедитесь, что на игровом сервере SCUM установлен модуль [AMURKA-PVE-SCUM/scum-rcon](https://github.com/AMURKA-PVE-SCUM/scum-rcon).
2. Откройте панель в браузере и войдите под учётной записью администратора.
3. Нажмите **«Добавить сервер»** и заполните данные **RCON** (IP, порт, пароль) и **FTP/SFTP** (хост, порт, логин, пароль, путь к папке сохранения/логов).
4. Нажмите **«Проверить RCON»** и **«Проверить FTP»**, сохраните сервер и войдите в панель управления.

---

## 🗄 Архитектура БД и резервное копирование

```text
/var/www/scum-panel/
├── database/
│   └── database.sqlite      # Локальная база панели (настройки, серверы, сценарии, аудит, очереди)
└── storage/
    └── app/scum/
        └── SCUM.db          # Игровая база SCUM (скачивается автоматически демоном scum-watch)
```

- **Автоматический бэкап:** скрипт [`deploy/backup.sh`](deploy/backup.sh) делает консистентный снимок SQLite (`.backup`), архивирует `.env` и `config/` в `/var/backups/scum-manager/` и удаляет архивы старше 7 дней:
  ```bash
  sudo bash /var/www/scum-panel/deploy/backup.sh
  ```

---

## 🔄 Обновление и диагностика

### Обновление панели

```bash
cd /var/www/scum-panel
sudo -u www-data git pull origin main
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
sudo supervisorctl restart scum-rcon scum-watch scum-scheduler
```

### Частые проблемы (Troubleshooting)

- **Логи демонов и приложения:**
  ```bash
  tail -f /var/www/scum-panel/storage/logs/supervisor-*.log
  tail -f /var/www/scum-panel/storage/logs/laravel.log
  ```
- **Ошибка `Permission denied` в `storage` или `database`:**
  ```bash
  sudo chown -R www-data:www-data /var/www/scum-panel/storage /var/www/scum-panel/database /var/www/scum-panel/bootstrap/cache
  sudo chmod -R 775 /var/www/scum-panel/storage /var/www/scum-panel/database /var/www/scum-panel/bootstrap/cache
  ```
- **Сброс или создание пароля администратора:**
  ```bash
  cd /var/www/scum-panel && sudo -u www-data php artisan panel:user
  ```
- **Проверка доступности RCON-порта с сервера панели:**
  ```bash
  nc -zv <IP_ИГРОВОГО_СЕРВЕРА> 7004
  ```

---

## 🧪 Тестирование и качество кода

```bash
php artisan test
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

---

## 💬 Поддержка и обратная связь

Если у вас возникли вопросы по установке, настройке или вы обнаружили ошибку в работе панели — пожалуйста, создайте обращение в разделе Issues:

👉 **[https://github.com/valerun541/scum-server-manager/issues](https://github.com/valerun541/scum-server-manager/issues)**

*(При сообщении об ошибке желательно приложить фрагмент лога из `storage/logs/laravel.log` или `storage/logs/supervisor-*.log` и указать версию PHP / ОС).*

---

## 📄 Лицензия

Проект распространяется под открытой лицензией [MIT](LICENSE).
