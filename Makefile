# Variables
CARGO_TOML = Cargo.toml
BINARY_NAME = hexahop
RELEASE_BIN = ./target/release/$(BINARY_NAME)
RUST_SRC = $(wildcard rust/*.rs)

# Arguments
MAP ?= 1

.PHONY: all clean help run-debug run-solve

# Default action: build in release mode
all: release

## Build: Just an alias for the release binary dependency
build: $(RELEASE_BIN)

# The binary depends on all .rs files
$(RELEASE_BIN): $(RUST_SRC)
	cargo build --release
	@touch $(RELEASE_BIN)


## Run Debug: Run the debugger (Example: make run-debug MAP=1 PATH=777)
run-debug:
	cargo run -- debug $(MAP) $(STEPS)

## Run Solve: Run the solver (Example: make run-solve MAP=1)
run-solve:
	cargo run -- solve $(MAP)

## Clean: Remove compiled artifacts
clean:
	cargo clean

## Help: Show available commands
help:
	@echo "Hexahop Project Management"
	@echo "--------------------------"
	@echo usage: make [target] [variables]
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'
