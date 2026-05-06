init: composer-install npm-install key-generate storage-link db-migrate moonshine-user

init-dev: composer-install key-generate storage-permissions storage-link db-migrate moonshine-user prepare-htaccess

composer-install:
	@echo "Installing PHP dependencies (composer install)"
	composer install

npm-install:
	@echo "Installing JS dependencies (npm install)"
	npm install

key-generate:
	@echo "Generating application key (php artisan key:generate)"
	php artisan key:generate

storage-permissions:
	@echo "Setting permissions for storage and bootstrap/cache"
	chmod -R 0777 storage bootstrap/cache

storage-link:
	@echo "Creating symbolic link for storage (php artisan storage:link)"
	php artisan storage:link

db-migrate:
	@echo "Running database migrations (php artisan migrate)"
	php artisan migrate --force

moonshine-user:
	@echo "Creating MoonShine user"
	php artisan moonshine:create-user

prepare-htaccess:
	@echo "Preparing .htaccess"
	mv .htaccess.example .htaccess
