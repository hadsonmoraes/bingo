# Atalhos para o projeto Bingo (host só precisa do Docker + Node)
#
#   make help          lista os comandos
#   make composer ARGS="install"        composer dentro do container
#   make artisan ARGS="test"            artisan dentro do container
#   make npm ARGS="run build"           npm via imagem node
#
# Todos os comandos rodam com o UID/GID do usuário do host para que os
# arquivos criados não fiquem com dono root.

UID  := $(shell id -u)
GID  := $(shell id -g)

.PHONY: help up down build composer artisan test pint tinker seed npm migrate worker-restart

help: ## Lista os comandos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

up: ## Sobe a aplicação (http://localhost:8000)
	docker compose up -d

down: ## Derruba a aplicação
	docker compose down

worker-restart: ## Reinicia o worker da fila (obrigatório após mudar código PHP)
	docker compose exec -T app php artisan queue:restart

build: ## (Re)constrói a imagem PHP
	docker compose build app

composer: ## Composer: make composer ARGS="require pacote"
	docker compose exec -T -e COMPOSER_HOME=/tmp/composer --user "$(UID):$(GID)" app composer $(ARGS)

artisan: ## Artisan: make artisan ARGS="migrate"
	docker compose exec -T app php artisan $(ARGS)

migrate: ## migrate:fresh --seed
	docker compose exec -T app php artisan migrate:fresh --seed

test: ## Roda a suíte de testes
	docker compose exec -T app php artisan test

pint: ## Formata o código com Pint
	docker compose exec -T app ./vendor/bin/pint

tinker: ## Tinker: make tinker ARGS="--execute='...'"
	docker compose exec -T app php artisan tinker $(ARGS)

npm: ## npm via Docker: make npm ARGS="run build"
	docker compose run --rm --user "$(UID):$(GID)" node npm $(ARGS)
