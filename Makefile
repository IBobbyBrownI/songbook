# ============================================================
# Songbook — Makefile
#
#   make init   — идемпотентно поднять/обновить проект:
#                 фронт-деп-ы (Lit) → бэк-деп-ы (composer) → пересборка образа
#                 → старт контейнеров → очистка Twig-кеша.
#                 Переиспользуй после правок визуала, чтобы применить изменения.
#
#   ВАЖНО: данные в БД НЕ ТРОГАЮТСЯ. Том `db_data` сохраняется при up/build/
#   restart/down. Создание таблиц (make migrate) идемпотентно (IF NOT EXISTS) и
#   не удаляет данные. Стирание/заливка демо — только явный `make seed`.
# ============================================================

COMPOSE := docker compose
PHP_SVC := php
DB_SVC  := database
LIT_URL := https://cdn.jsdelivr.net/gh/lit/dist@3/all/lit-all.min.js
LIT_DST := public/js/vendor/lit-all.min.js
APP_URL := http://localhost:8000

.DEFAULT_GOAL := help
.PHONY: help init setup up down build restart deps front cache-clear cc ready migrate seed sh logs ps

help: ## Показать список команд
	@grep -hE '^[a-zA-Z0-9_-]+:.*## ' $(MAKEFILE_LIST) \
	  | awk 'BEGIN{FS=":.*## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

init: front deps build up cache-clear ready ## Поднять/обновить проект (деп-ы+пересборка+кеш). БД не трогается
	@echo "✓ init готов — $(APP_URL)"

setup: init migrate ## Первый запуск с нуля: init + миграции (без seed). Демо-данные потом: make seed
	@echo "✓ setup готов. Демо-данные (необязательно): make seed"

front: ## Фронт-зависимости: вендоренный Lit-бандл (скачивается, только если отсутствует)
	@mkdir -p $(dir $(LIT_DST))
	@if [ -f "$(LIT_DST)" ]; then echo "→ Lit бандл на месте"; \
	else echo "→ скачиваю Lit бандл"; curl -fsSL -o "$(LIT_DST)" "$(LIT_URL)"; fi

deps: ## Бэк-зависимости: composer install (одноразовый образ composer:2, без Node)
	@echo "→ composer install"
	@docker run --rm -v "$(CURDIR)":/app -w /app composer:2 install \
	  --no-interaction --prefer-dist --no-progress --ignore-platform-req=ext-pdo

build: ## Пересобрать образ php (данные БД не трогаются)
	@$(COMPOSE) build

up: ## Старт контейнеров в фоне (том db_data сохраняется)
	@$(COMPOSE) up -d

cache-clear: ## Очистить Twig-кеш (var/twig). Данные БД не затрагиваются
	@$(COMPOSE) exec -T $(PHP_SVC) sh -lc 'rm -rf var/twig' 2>/dev/null || rm -rf var/twig
	@echo "→ Twig-кеш очищен"

cc: cache-clear ## Алиас: cache-clear

restart: ## Перезапустить контейнеры (данные сохраняются)
	@$(COMPOSE) restart

down: ## Остановить контейнеры. ДАННЫЕ СОХРАНЯЮТСЯ (том не удаляется)
	@$(COMPOSE) down

migrate: ## Применить миграции (CREATE IF NOT EXISTS — данные не удаляются). Нужно при первом запуске
	@for f in migrations/*.sql; do \
	  echo "→ $$f"; \
	  $(COMPOSE) exec -T $(DB_SVC) sh -lc 'exec mysql -u root -p"$$MYSQL_ROOT_PASSWORD" "$$MYSQL_DATABASE"' < "$$f" || exit 1; \
	done
	@echo "✓ миграции применены"

seed: ## ⚠ СТИРАЕТ песни/артистов и зальёт демо-данные. Только вручную, НЕ часть init
	@echo "⚠  seed УДАЛИТ все песни/артистов и зальёт демо. Ctrl-C для отмены…"; sleep 3
	@$(COMPOSE) exec -T $(PHP_SVC) php seed/seed.php
	@echo "✓ демо-данные залиты"

sh: ## Шелл внутри php-контейнера
	@$(COMPOSE) exec $(PHP_SVC) sh

logs: ## Логи контейнеров (follow)
	@$(COMPOSE) logs -f

ps: ## Статус контейнеров
	@$(COMPOSE) ps

ready: ## (внутр.) Дождаться ответа приложения
	@printf "→ жду приложение"; \
	for i in $$(seq 1 30); do \
	  if curl -fsS $(APP_URL) >/dev/null 2>&1; then echo " — готово"; exit 0; fi; \
	  printf "."; sleep 1; \
	done; echo " — таймаут (см. make logs)"
