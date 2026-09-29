# Patchbay on your own machine. `make up` is the whole setup:
#
#   make up
#
# It starts the stack and makes https://patchbay.localhost:8443 work in a
# browser. Where Herd, Valet or mkcert have already put a certificate authority
# in this machine's trust store, it signs with that one and nothing else is
# needed. Otherwise Caddy generates an authority and `caddy trust` installs it,
# which asks for a password once.
#
# Plain compose commands still work; follow them with `make trust`.

DASHBOARD := https://patchbay.localhost:$(or $(PATCHBAY_HTTPS_PORT),8443)

.DEFAULT_GOAL := help
.PHONY: help up down restart rebuild logs certs trust untrust shell

help: ## Show this help
	@grep -hE '^[a-z-]+:.*##' $(MAKEFILE_LIST) | \
		awk -F':.*##' '{printf "  make %-10s %s\n", $$1, $$2}'

up: ## Start the stack and make its certificate trusted
	@bin/patchbay-certs issue
	docker compose up -d
	@$(MAKE) --no-print-directory trust

down: ## Stop the stack, keeping the database and the certificates
	docker compose down

restart: ## Recreate the containers
	docker compose up -d --force-recreate

# The code is copied into the image when it is built, not mounted, so a change
# here, or a newer package after `composer update`, needs a rebuild.
rebuild: ## Rebuild the image, picking up code and dependency changes
	docker compose build
	docker compose up -d
	@$(MAKE) --no-print-directory trust

logs: ## Follow the logs of every service
	docker compose logs -f

certs: ## Reissue the certificates, using PATCHBAY_CA_CERT if set
	@bin/patchbay-certs issue
	@$(MAKE) --no-print-directory trust

trust: ## Make the certificate verify, however that has to happen
	@bin/patchbay-certs ensure; \
	case $$? in \
		0) ;; \
		10) $(MAKE) --no-print-directory caddy-trust ;; \
		*) exit 1 ;; \
	esac

untrust: ## Remove Caddy's authority from the trust store again
	caddy untrust --address 127.0.0.1:2019

shell: ## Open a shell in the application container
	docker compose exec app bash

.PHONY: caddy-trust
caddy-trust:
	@if ! command -v caddy >/dev/null 2>&1; then \
		echo "No trusted certificate authority was found on this machine, so"; \
		echo "Caddy generated one - and installing it needs Caddy out here:"; \
		echo; \
		echo "    brew install caddy      # or https://caddyserver.com/docs/install"; \
		echo; \
		echo "Alternatively point at an authority you already trust:"; \
		echo; \
		echo "    PATCHBAY_CA_CERT=root.pem PATCHBAY_CA_KEY=root.key make certs"; \
		exit 1; \
	fi
	@echo "Installing the authority Caddy generated; it will ask for your password."
	@caddy trust --address 127.0.0.1:2019
	@echo
	@echo "Quit your browser completely and reopen it, then visit"
	@echo "$(DASHBOARD) - a reload alone keeps the warning it cached."
