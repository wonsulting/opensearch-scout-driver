.PHONY: up down wait test coverage style-check static-analysis help

.DEFAULT_GOAL := help

## opensearch config
OPENSEARCH_VERSION ?= 2
OPENSEARCH_CONTAINER_IMAGE := opensearchproject/opensearch:${OPENSEARCH_VERSION}
OPENSEARCH_CONTAINER_NAME := opensearch-scout-driver-opensearch
OPENSEARCH_HOST_PORT := 9200
OPENSEARCH_DISCOVERY_TYPE := single-node

up: ## Start containers
	@printf "\033[93m→ Starting ${OPENSEARCH_CONTAINER_NAME} container\033[0m\n"
	@docker run --rm -d \
		--name ${OPENSEARCH_CONTAINER_NAME} \
		-p ${OPENSEARCH_HOST_PORT}:9200 \
		-e discovery.type=${OPENSEARCH_DISCOVERY_TYPE} \
		-e DISABLE_SECURITY_PLUGIN=true \
		-e DISABLE_INSTALL_DEMO_CONFIG=true \
		${OPENSEARCH_CONTAINER_IMAGE}
	@printf "\033[92m✔︎ ${OPENSEARCH_CONTAINER_NAME} is started\033[0m\n"

down: ## Stop containers
	@printf "\033[93m→ Stopping containers\033[0m\n"
	@docker stop ${OPENSEARCH_CONTAINER_NAME}
	@printf "\033[92m✔︎ Containers are stopped\033[0m\n"

wait: ## Wait until containers are ready
	@printf "\033[93m→ Waiting for ${OPENSEARCH_CONTAINER_NAME} container\033[0m\n"
	@until curl -fsS "127.0.0.1:${OPENSEARCH_HOST_PORT}/_cluster/health?wait_for_status=yellow&timeout=60s"; do \
		printf "\033[91m✘ ${OPENSEARCH_CONTAINER_NAME} is not ready, waiting...\033[0m\n"; \
		sleep 5; \
	done
	@printf "\n\033[92m✔︎ ${OPENSEARCH_CONTAINER_NAME} is ready\033[0m\n"

test: ## Run tests
	@printf "\033[93m→ Running tests\033[0m\n"
	@composer test
	@printf "\n\033[92m✔︎ Tests are completed\033[0m\n"

coverage: ## Run tests and generate the code coverage report
	@printf "\033[93m→ Running tests and generating the code coverage report\033[0m\n"
	@XDEBUG_MODE=coverage composer test-coverage
	@printf "\n\033[92m✔︎ Tests are completed and the report is generated\033[0m\n"

style-check: ## Check the code style
	@printf "\033[93m→ Checking the code style\033[0m\n"
	@composer check-style
	@printf "\n\033[92m✔︎ Code style is checked\033[0m\n"

static-analysis: ## Do static code analysis
	@printf "\033[93m→ Analysing the code\033[0m\n"
	@composer analyse
	@printf "\n\033[92m✔︎ Code static analysis is completed\033[0m\n"

help: ## Show help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
