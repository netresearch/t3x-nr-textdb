.PHONY: help build build-release clean test install check-deps generate-header build-all

# Default target
.DEFAULT_GOAL := help

# Detect platform
UNAME_S := $(shell uname -s)
UNAME_M := $(shell uname -m)

# Library naming
ifeq ($(UNAME_S),Linux)
    LIB_NAME = libxliff_parser.so
    PLATFORM_DIR = linux64
    ifeq ($(UNAME_M),aarch64)
        PLATFORM_DIR = linux-arm64
    endif
else ifeq ($(UNAME_S),Darwin)
    LIB_NAME = libxliff_parser.dylib
    PLATFORM_DIR = darwin64
    ifeq ($(UNAME_M),arm64)
        PLATFORM_DIR = darwin-arm64
    endif
else ifeq ($(OS),Windows_NT)
    LIB_NAME = xliff_parser.dll
    PLATFORM_DIR = win64
endif

# Paths
TARGET_DIR = target
OUTPUT_DIR = ../../Resources/Private/Bin/$(PLATFORM_DIR)
HEADER_FILE = xliff_parser.h

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

check-deps: ## Check required dependencies
	@command -v cargo >/dev/null 2>&1 || { echo "Error: cargo not found. Install Rust from https://rustup.rs/"; exit 1; }
	@command -v cbindgen >/dev/null 2>&1 || { echo "Warning: cbindgen not found. Run: cargo install cbindgen"; }
	@echo "✓ Dependencies OK"

build: check-deps ## Build debug version
	cargo build

build-release: check-deps ## Build optimized release version
	cargo build --release

generate-header: ## Generate C header file
	@command -v cbindgen >/dev/null 2>&1 || { echo "Installing cbindgen..."; cargo install cbindgen; }
	cbindgen --config cbindgen.toml --crate xliff_parser --output $(HEADER_FILE)
	@echo "Generated header: $(HEADER_FILE)"

test: ## Run tests
	cargo test
	cargo test --release

clean: ## Clean build artifacts
	cargo clean
	rm -f $(HEADER_FILE)
	rm -rf ../../Resources/Private/Bin/*/

install: build-release generate-header ## Install library to extension directory
	@echo "Installing to: $(OUTPUT_DIR)"
	@mkdir -p $(OUTPUT_DIR)
	@cp $(TARGET_DIR)/release/$(LIB_NAME) $(OUTPUT_DIR)/
	@cp $(HEADER_FILE) ../../Build/Rust/
	@echo "✓ Installed: $(OUTPUT_DIR)/$(LIB_NAME)"
	@echo "✓ Header: ../../Build/Rust/$(HEADER_FILE)"

# Cross-compilation targets
build-linux-x64: ## Cross-compile for Linux x86_64
	cargo build --release --target x86_64-unknown-linux-gnu
	@mkdir -p ../../Resources/Private/Bin/linux64
	@cp target/x86_64-unknown-linux-gnu/release/libxliff_parser.so ../../Resources/Private/Bin/linux64/

build-linux-arm64: ## Cross-compile for Linux ARM64
	cargo build --release --target aarch64-unknown-linux-gnu
	@mkdir -p ../../Resources/Private/Bin/linux-arm64
	@cp target/aarch64-unknown-linux-gnu/release/libxliff_parser.so ../../Resources/Private/Bin/linux-arm64/

build-macos-x64: ## Cross-compile for macOS Intel
	cargo build --release --target x86_64-apple-darwin
	@mkdir -p ../../Resources/Private/Bin/darwin64
	@cp target/x86_64-apple-darwin/release/libxliff_parser.dylib ../../Resources/Private/Bin/darwin64/

build-macos-arm64: ## Cross-compile for macOS Apple Silicon
	cargo build --release --target aarch64-apple-darwin
	@mkdir -p ../../Resources/Private/Bin/darwin-arm64
	@cp target/aarch64-apple-darwin/release/libxliff_parser.dylib ../../Resources/Private/Bin/darwin-arm64/

build-windows-x64: ## Cross-compile for Windows x64
	cargo build --release --target x86_64-pc-windows-msvc
	@mkdir -p ../../Resources/Private/Bin/win64
	@cp target/x86_64-pc-windows-msvc/release/xliff_parser.dll ../../Resources/Private/Bin/win64/

build-all: generate-header build-linux-x64 build-linux-arm64 build-macos-x64 build-macos-arm64 build-windows-x64 ## Build for all platforms
	@echo "✓ Built for all platforms"

# Development helpers
dev: build ## Build and run tests in development mode
	cargo test

watch: ## Watch for changes and rebuild
	cargo watch -x build -x test

bench: ## Run benchmarks
	cargo bench

size: build-release ## Show library size
	@ls -lh $(TARGET_DIR)/release/$(LIB_NAME)

# CI/CD helpers
ci-test: check-deps ## Run tests in CI mode
	cargo test --release --verbose

ci-build: check-deps generate-header build-release ## Build for CI
	@echo "Build complete for $(PLATFORM_DIR)"
