# SCUM Server Manager (SSM)

> **Enterprise-grade веб-панель управления игровыми серверами SCUM** на базе Laravel 12, Alpine.js, Tailwind CSS и прямого асинхронного RCON/FTP шлюза.

---

## 🌟 Возможности

- **Интерактивная RCON-консоль (Interactive CLI)**:
  - Прямой защищённый шлюз выполнения команд в реальном времени через фоновый демон.
  - Категоризированный каталог из официальных команд сервера SCUM (238 команд): мониторинг, игроки, транспорт, базы, модерация, существа/роботы, спавн, эвенты.
  - Интерактивный конструктор спавна лута, техники, NPC и телепортации с визуальным предпросмотром команды.
  - История команд с просмотром полных ответов сервера и возможностью повторного выполнения.
- **Интеллектуальный каталог игровых объектов**:
  - Свыше 2 400 предметов, вся доступная техника, существа и ключевые точки интереса (POI).
  - Умный поиск с поддержкой синонимов, сленга SCUM («калаш», «сишка», «дигл») и русской/английской раскладки.
  - Оптимизированные WebP-иконки для каждого предмета и транспортного средства.
- **База данных SCUM (SCUM.db & Telemetry)**:
  - Глубокая аналитика игроков: досье, инвентарь, валюта (Cash/Gold), слава, время в игре.
  - Инспектор баз и флагов с координатами и элементами строений.
  - Инспектор транспорта с отслеживанием владельцев и локаций.
- **Автоматизация и триггеры**:
  - Настраиваемые расписания (Schedules) и автоматические эвенты.
  - Airdrop Spawner и система вознаграждений.
  - Интеграция с магазином WARGM и логами сервера.
- **Безопасность корпоративного уровня**:
  - Защита IP Whitelist с поддержкой CIDR-подсетей.
  - Двухфакторная аутентификация (2FA / TOTP).
  - Защита от Brute-force атак и разграничение прав доступа.
  - Полный аудит действий администраторов (Audit Logs).

---

## 🛠 Технологический стек

- **Backend**: PHP 8.4+, Laravel 12
- **Database**: SQLite (Enterprise WAL Mode)
- **Frontend**: Blade Components, Alpine.js 3, Tailwind CSS 4, Vite
- **Services & Daemons**: Systemd / Supervisor, RCON Daemon, FTP Watcher

---

## 🚀 Быстрый старт и установка

### 1. Требования к серверу
- PHP >= 8.3 (расширения: `php-sqlite3`, `php-curl`, `php-mbstring`, `php-xml`, `php-bcmath`)
- Composer >= 2.6
- Node.js >= 18.x & NPM >= 9.x
- Nginx / Apache / Caddy

### 2. Клонирование репозитория
```bash
git clone https://github.com/your-username/scum-server-manager.git /var/www/scum-panel
cd /var/www/scum-panel
```

### 3. Установка зависимостей
```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 4. Настройка окружения
```bash
cp .env.example .env
php artisan key:generate
```

Отредактируйте `.env`:
```ini
APP_NAME="SCUM Server Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### 5. Инициализация базы данных и создание администратора
```bash
touch database/database.sqlite
php artisan migrate --force
php artisan panel:user:create
```

### 6. Настройка прав доступа
```bash
chown -R www-data:www-data storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache database
```

### 7. Запуск фоновых демонов
Для постоянной работы RCON-очереди и синхронизации создайте systemd-сервисы (примеры в папке `deploy/`):
```bash
# Демон RCON
php artisan scum:rcon --poll=150

# Демон синхронизации логов и базы
php artisan scum:watch --poll=500

# Планировщик задач
php artisan schedule:work
```

---

## 🧪 Тестирование и качество кода

Панель полностью покрыта автоматическими тестами и статическим анализом:

```bash
# Запуск юнит- и функциональных тестов
php artisan test

# Статический анализ PHPStan (Level 6+)
vendor/bin/phpstan analyse --memory-limit=1G

# Проверка стандартов кода Laravel Pint
vendor/bin/pint --test
```

---

## 📄 Лицензия

Проект распространяется под лицензией MIT. Подробности в файле [LICENSE](LICENSE).
