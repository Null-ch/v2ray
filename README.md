
## Описание проекта

Проект представляет собой веб-приложение на базе Laravel 12, предназначенное для работы с V2Ray. Приложение использует Docker для контейнеризации и включает в себя следующие компоненты:

- **PHP 8.4-FPM** - сервер приложений
- **Nginx** - веб-сервер
- **MySQL 8** - база данных
- **Laravel 12** - PHP фреймворк
- **Vite** - сборщик фронтенда (Tailwind CSS)

## Требования

Перед началом работы убедитесь, что у вас установлены:

- **Docker** (версия 20.10 или выше)
- **Docker Compose** (версия 2.0 или выше)
- **Git**

## Установка и настройка

### 1. Клонирование репозитория

```bash
git clone <repository-url>
cd v2ray
```

### 2. Создание файла .env

**⚠️ ВАЖНО: Перед сборкой проекта обязательно создайте файл `.env` в директории `src`!**

Создайте файл `src/.env` из .env.example:

```

### 3. Сборка  и запуск проекта

Соберите Docker-образы и запустите контейнеры:

```bash
docker compose up -d --build
```
```bash
docker exec -it v2ray_test_app bash
```
```bash
php artisan migrate:fresh --force
php artisan db:seed
```

При первом запуске Dockerfile автоматически выполнит:
- Установку зависимостей Composer
- Запуск PHP-FPM

### 4. Сборка фронтенда (опционально)

Если нужно собрать фронтенд-ресурсы, выполните:

```bash
docker compose exec app npm install
docker compose exec app npm run build
```

Или для разработки с hot-reload:

```bash
docker compose exec app npm run dev
```

## Использование

Использование

После успешного запуска приложение будет доступно по адресам:

HTTP: http://localhost:8080
HTTPS: https://localhost:8443
 (если настроен SSL)

⚠️ Важно при работе через Docker:
Контейнер Nginx в Docker обычно слушает порты внутри сети контейнеров (например, 80/443). Чтобы приложение было доступно извне:

Можно настроить проброс портов в docker-compose.yml (как в примере выше: 8080:80, 8443:443)
Или использовать ваш локальный Nginx / Traefik / Caddy как прокси, который перенаправляет запросы на контейнер Nginx (proxy_pass http://localhost:8080;)
Важно, чтобы внешние порты совпадали с портами, указанными в прокси, иначе приложение не будет доступно

### Доступ к базе данных

- **Хост**: `localhost`
- **Порт**: `3307`
- **База данных**: `v2ray`
- **Пользователь**: `v2ray`
- **Пароль**: `CHvcuXdcHECcfMrK`

### Полезные команды

#### Просмотр логов

```bash
# Логи всех сервисов
docker compose logs -f

# Логи конкретного сервиса
docker compose logs -f app
docker compose logs -f web
docker compose logs -f db
```

#### Выполнение Artisan команд

```bash
docker compose exec app php artisan <command>
```

Примеры:
```bash
# Очистка кеша
docker compose exec app php artisan cache:clear

# Запуск миграций
docker compose exec app php artisan migrate

# Создание контроллера
docker compose exec app php artisan make:controller ExampleController
```

#### Выполнение Composer команд

```bash
docker compose exec app composer install
docker compose exec app composer update
```

#### Выполнение NPM команд

```bash
docker compose exec app npm install
docker compose exec app npm run build
docker compose exec app npm run dev
```

#### Доступ к контейнеру

```bash
docker compose exec app bash
```

#### Остановка проекта

```bash
docker compose down
```

#### Остановка с удалением volumes (⚠️ удалит данные БД)

```bash
docker compose down -v
```

## Структура проекта

```
v2ray/
├── docker compose.yml      # Конфигурация Docker Compose
├── Dockerfile              # Образ PHP-FPM приложения
├── nginx/                  # Конфигурация Nginx
│   └── conf.d/
│       └── default.conf
└── src/                    # Laravel приложение
    ├── app/                # Код приложения
    ├── bootstrap/          # Файлы инициализации
    ├── config/             # Конфигурационные файлы
    ├── database/           # Миграции и сидеры
    ├── public/             # Публичная директория
    ├── resources/          # Ресурсы (views, css, js)
    ├── routes/             # Маршруты
    ├── storage/            # Хранилище файлов и логов
    ├── tests/              # Тесты
    ├── vendor/             # Зависимости Composer
    ├── .env                # ⚠️ Файл переменных окружения (создать вручную!)
    ├── artisan             # CLI Laravel
    ├── composer.json       # Зависимости PHP
    └── package.json        # Зависимости Node.js
```

## Разработка

### Локальная разработка без Docker

Если вы хотите разрабатывать без Docker, выполните в директории `src`:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

### Запуск тестов

```bash
docker compose exec app php artisan test
```

## Устранение неполадок

### Проблемы с правами доступа

Если возникают проблемы с правами доступа к директориям `storage` и `bootstrap/cache`:

```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Проблемы с подключением к БД

Убедитесь, что:
1. Контейнер `db` запущен: `docker compose ps`
2. В `.env` указаны правильные параметры подключения
3. Хост БД в `.env` указан как `db` (имя сервиса в docker compose)

### Очистка и пересборка

Если нужно полностью пересобрать проект:

```bash
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

## Лицензия

См. файл [LICENSE](LICENSE) для подробной информации.
