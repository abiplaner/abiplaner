.PHONY: help build up down restart shell composer artisan npm test migrate seed fresh logs

help: ## Zeige Hilfe
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: ## Docker Images bauen
	docker compose build

up: ## Container starten
	docker compose up -d

down: ## Container stoppen
	docker compose down

restart: down up ## Container neu starten

shell: ## Shell im App-Container öffnen
	docker compose exec app sh

shell-db: ## Shell im DB-Container öffnen
	docker compose exec db mysql -u abiplaner -psecret abiplaner

composer: ## Composer install
	docker compose exec app composer install

composer-update: ## Composer update
	docker compose exec app composer update

artisan: ## Artisan Befehl ausführen (z.B. make artisan cmd="migrate")
	docker compose exec app php artisan $(cmd)

npm-install: ## NPM Dependencies installieren
	docker compose exec node npm install

npm-dev: ## NPM Dev-Server starten
	docker compose exec node npm run dev

npm-build: ## Frontend für Produktion bauen
	docker compose exec node npm run build

test: ## PHPUnit Tests ausführen
	docker compose exec app php artisan test

migrate: ## Datenbank-Migrationen ausführen
	docker compose exec app php artisan migrate

migrate-fresh: ## Datenbank zurücksetzen und neu migrieren
	docker compose exec app php artisan migrate:fresh

seed: ## Datenbank mit Test-Daten füllen
	docker compose exec app php artisan db:seed

fresh: migrate-fresh seed ## DB zurücksetzen, migrieren und seeden

logs: ## Logs anzeigen
	docker compose logs -f

logs-app: ## App-Logs anzeigen
	docker compose logs -f app

logs-nginx: ## Nginx-Logs anzeigen
	docker compose logs -f nginx

logs-db: ## DB-Logs anzeigen
	docker compose logs -f db

install: build up composer npm-install key migrate seed ## Komplette Installation

key: ## Application Key generieren
	docker compose exec app php artisan key:generate

cache-clear: ## Cache leeren
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear

permissions: ## Berechtigungen setzen
	docker compose exec app chown -R www:www /var/www/storage
	docker compose exec app chown -R www:www /var/www/bootstrap/cache
