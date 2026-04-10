# ============================================================
# Frontend - Build toolchain
# ============================================================
.PHONY: install build dev clean

NPX := npx --yes

# --- Install deps ---
node_modules: package.json
	@if [ -f package-lock.json ]; then npm ci; else npm install; fi
	@touch node_modules

install: node_modules

# --- Production build (compressed, no source-map) ---
build: node_modules
	$(NPX) sass scss/style.scss public/assets/css/style.css \
		--style=compressed --no-source-map
	@echo "[frontend] scss -> css (prod)"

# --- Dev watch (expanded + source-map, auto-reload friendly) ---
dev: node_modules
	$(NPX) sass scss/style.scss public/assets/css/style.css \
		--watch --source-map

# --- Clean generated assets ---
clean:
	rm -f public/assets/css/style.css public/assets/css/style.css.map
	@echo "[frontend] cleaned generated css"
