# Laravel 13 Backend Template

Шаблон для быстрого старта разработки бэкенд-приложений на **Laravel 13**.

---

## 📦 Требования

Для запуска проекта необходимо установить:

- **PHP** ≥ 8.4
- **Node.js** ≥ 20
- **MySQL** ≥ 8.0 или **PostgreSQL** ≥ 15

---

## ⚙️ Настройка OpenServer

Для запуска проекта в среде OpenServer выполните следующие шаги:

1. Скопируйте папку `.osp.example` в `.osp`.
2. В файлах `.osp/project.ini`, `.osp/tasks.ini` и в имени конфигурационного файла `.osp/Nginx/domain.loc.conf` замените `domain.loc` на ваш локальный домен.
3. В настройках OpenServer активируйте модули **Nginx** и **PHP**.
4. Перезапустите OpenServer для применения изменений.

---

## 🚀 Установка и запуск проекта

### 1. Клонирование репозитория

```shell
git clone [URL репозитория]
cd [название-папки-проекта]
```

### 2. Установка зависимостей PHP

```shell
composer install
```

### 3. Установка зависимостей JavaScript

```shell
npm install
```

### 4. Настройка окружения

- Скопируйте файл `.env.example` в `.env`
- Настройте параметры базы данных в `.env`
- Сгенерируйте ключ приложения, эта команда генерирует `APP_KEY=base64:***` в .env файле:
```shell
php artisan key:generate
```

- Создайте symlink на папку storage/app/public (настройки для хранилища s3 описаны в конце):
```shell
php artisan storage:link
```

### 5. Запуск миграций базы данных

```shell
php artisan migrate
```

### 6. Создание администратора Moonshine

```shell
php artisan moonshine:create-user
```
Команда создаст администратора согласно env переменным:
- `MOONSHINE_USERNAME`
- `MOONSHINE_NAME`
- `MOONSHINE_PASSWORD`

Либо доступы можно передать атрибутами:
- `{--u|username= : Username}`
- `{--N|name= : Name}`
- `{--p|password= : Password}`


---

## 🖥 Локальный запуск

1. Запуск Laravel-сервера:
   ```bash
   php artisan serve
   ```

2. Запуск Vite-сборщика:
   ```bash
   npm run dev
   ```

Moonshine Admin Panel будет доступна по адресу:  
[http://localhost:8000/admin](http://localhost:8000/admin)

---

## Управление очередями и sheduler

- Запуск обработки очередей

```shell
php artisan queue:work
```

- Обработка планировщика

```shell
php artisan schedule:run # Запускать в crontab каждую минуту
# Или
php artisan schedule:work # Запустить 1 раз, будет работать как очередь
```

  

## Работа в Cross-Domain режиме
Предпочтителен режим работы на отдельном от Frontend приложения домене. Для этого надо прописать в CORS_ALLOWED_ORIGINS https домен Frontend приложения, и разрешить кросс-доменные cookies:
```dotenv
CORS_ALLOWED_ORIGINS=https://FRONTEND_DOMAIN
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=None
```

Либо, если на одном домене, то Laravel должен обрабатывать роуты по путям `/admin/`, `/vendor/`, `/storage/` и `/api/`, все остальные могут принадлежать Frontend приложению из другого репозитория.

## Настройка хранилища S3

Если сервер должен быть масштабируемым обычный storage не подойдет и нужно настроить подключение к S3 хранилищу с помощью ENV переменных:

- `FILESYSTEM_DISK`=`s3`
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`
- `AWS_BUCKET`
- `AWS_URL`
- `AWS_ENDPOINT`
- `AWS_USE_PATH_STYLE_ENDPOINT`

---
