.PHONY: help build up down restart logs shell-backend shell-frontend test lint lint-fix format clean

# Default target
help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-20s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Docker operations
build: ## Build all containers
	docker-compose build

up: ## Start all containers
	docker-compose up

down: ## Stop all containers
	docker-compose down

restart: ## Restart all containers
	docker-compose restart

logs: ## Show logs for all containers
	docker-compose logs -f

logs-backend: ## Show backend logs
	docker-compose logs -f backend

logs-frontend: ## Show frontend logs
	docker-compose logs -f frontend

# Shell access
shell-backend: ## Access backend container shell
	docker-compose exec backend bash

shell-frontend: ## Access frontend container shell
	docker-compose exec frontend sh

shell-mysql: ## Access MySQL shell
	docker-compose exec mysql mysql -u fragfolio -ppassword fragfolio

# Database operations
db-migrate: ## Run database migrations
	docker-compose exec backend php artisan migrate

db-seed: ## Run database seeders
	docker-compose exec backend php artisan db:seed

db-reset: ## Reset database with fresh migrations and seeds
	docker-compose exec backend php artisan migrate:fresh --seed

db-schema: ## Apply database schema using sqldef
	docker-compose exec backend mysqldef -h mysql -u fragfolio -ppassword fragfolio --file /var/www/html/sqldef/schema.sql

db-schema-test: ## Apply database schema to test database using sqldef
	docker-compose exec backend mysqldef -h mysql -u fragfolio -ppassword fragfolio_test --file /var/www/html/sqldef/schema.sql

# Testing
test: ## Run all tests
	$(MAKE) test-backend
	$(MAKE) test-frontend

test-backend: ## Run backend tests
	$(MAKE) db-schema-test
	docker-compose exec backend composer test

test-frontend: ## Run frontend tests
	docker-compose exec frontend npm test

# Linting and formatting
lint: ## Run all linting
	$(MAKE) lint-backend
	$(MAKE) lint-frontend

lint-backend: ## Run backend linting
	docker-compose exec backend composer lint
	docker-compose exec backend composer stan

lint-frontend: ## Run frontend linting
	docker-compose exec frontend npm run lint
	docker-compose exec frontend npm run format:check

lint-fix: ## Fix all linting issues
	$(MAKE) lint-fix-backend
	$(MAKE) lint-fix-frontend

lint-fix-backend: ## Fix backend linting issues
	docker-compose exec backend composer lint:fix

lint-fix-frontend: ## Fix frontend linting issues
	docker-compose exec frontend npm run lint:fix

format: ## Format all code
	$(MAKE) format-backend
	$(MAKE) format-frontend

format-backend: ## Format backend code
	docker-compose exec backend composer lint:fix

format-frontend: ## Format frontend code
	docker-compose exec frontend npm run format

# Development
dev: ## Start development environment
	docker-compose up -d

dev-logs: ## Start development environment with logs
	docker-compose up

install: ## Install dependencies
	$(MAKE) install-backend
	$(MAKE) install-frontend

install-backend: ## Install backend dependencies
	docker-compose exec backend composer install

install-frontend: ## Install frontend dependencies
	docker-compose exec frontend npm install

# Artisan commands
artisan: ## Run artisan command (usage: make artisan COMMAND="command here")
	docker-compose exec backend php artisan $(COMMAND)

# Clean up
clean: ## Clean up containers and volumes
	docker-compose down -v
	docker system prune -f

clean-all: ## Clean up everything including images
	docker-compose down -v --rmi all
	docker system prune -a -f

# Quick development setup
setup: ## Initial project setup
	$(MAKE) build
	$(MAKE) up
	sleep 10
	$(MAKE) db-schema
	$(MAKE) install
	@echo "🎉 Development environment is ready!"
	@echo "Backend: http://localhost:8002"
	@echo "Frontend: http://localhost:3002"
	@echo "phpMyAdmin: http://localhost:8082"
