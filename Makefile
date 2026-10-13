# Reproducible packaging for plug_cbbeforeregverify: the ZIP bytes depend only on the
# packaged files and one timestamp, never on the machine that built it, so a release can be
# rebuilt from its tag and still hash to the sha256 published for it.
# The workflow and its rationale are in README.md, section "Build".
#
# Two things differ from a stock Joomla extension, both forced by the CB manifest:
#
#   1. A <cbinstall> manifest spends <version> on the Community Builder compatibility
#      version and <release> on the extension's own semver. The release version - the
#      tag and the ZIP name - is therefore <release>.
#      Reading <version> here would package 0.1.1 under the name 2.11.0.
#   2. CB installs a plugin from the contents of its plugin directory, so the archive
#      root is $(PLUGIN_DIR), not the repository root. PACKAGE_FILES is relative to
#      that directory and the packager is handed it as the archive root.

SHELL := /bin/sh

EXT_NAME := plug_cbbeforeregverify
PLUGIN_DIR := components/com_comprofiler/plugin/user/plug_cbbeforeregverify
MANIFEST := $(PLUGIN_DIR)/cbbeforeregverify.xml

# One explicit list of what ships. Never a directory. Paths are relative to
# $(PLUGIN_DIR) because that is the archive root - see note 2 above.
#
# LICENSE is deliberately absent: CB installs the files its manifest <files> block
# declares, and shipping one it does not declare puts a file in the package that
# never lands anywhere. The licence is carried by the repository, not the package.
PACKAGE_FILES := \
	cbbeforeregverify.xml \
	cbbeforeregverify.php \
	component.cbbeforeregverify.php \
	install.cbbeforeregverify.php \
	index.html \
	language/index.html \
	language/default_language/index.html \
	language/default_language/admin_language.php \
	language/default_language/language.php \
	library/index.html \
	library/CBBeforeRegVerify.php \
	library/Table/index.html \
	library/Table/VerificationTable.php \
	library/Trigger/index.html \
	library/Trigger/UserTrigger.php \
	templates/index.html \
	templates/default/index.html \
	templates/default/step_code.php \
	templates/default/step_email.php

JZIP ?= tools/jzip.php
ZIP_LEVEL ?= 9
# `:=`, not `?=`: `clean` and dist_release `rm -rf` paths under these, so an exported
# BUILD_DIR or INSTALL_DIR in the environment must not redirect them. The command line
# still can, on purpose: the output location does not change the package bytes, so they
# are the only build variables RELEASE_LOCKED leaves free.
INSTALL_DIR := installation
BUILD_DIR := build
# `make -e` lets the environment override `:=` too, so refuse that origin outright.
ifneq ($(filter environment,$(firstword $(origin INSTALL_DIR)) $(firstword $(origin BUILD_DIR))),)
$(error INSTALL_DIR and BUILD_DIR may be set on the command line, not from the environment)
endif
# An empty value would turn `rm -rf "$(BUILD_DIR)"` into `rm -rf ""`, and `$(INSTALL_DIR)/dev`
# into `/dev`.
ifeq ($(strip $(INSTALL_DIR)),)
$(error INSTALL_DIR must not be empty)
endif
ifeq ($(strip $(BUILD_DIR)),)
$(error BUILD_DIR must not be empty)
endif
# dist_release `rm -rf`s its staging directory after packaging, so an output directory at or
# under it would be deleted with it. abspath normalises `./build` and `build/` alike.
ifneq ($(filter $(abspath $(BUILD_DIR))/release $(abspath $(BUILD_DIR))/release/%,$(abspath $(INSTALL_DIR))/release $(abspath $(INSTALL_DIR))/dev),)
$(error INSTALL_DIR/release and INSTALL_DIR/dev must not be at or under BUILD_DIR/release)
endif

RELEASE_NOTES ?= docs/RELEASE.md

# <release> is the extension version; <version> is the CB compatibility version.
# `override` is load-bearing. The tag and the ZIP name derive
# from this, and deriving it from the manifest instead of typing it is the whole point
# of `release`. Without `override`, `make release VERSION=x` tags a version the manifest
# never declared - and refusing to package it afterwards does not remove the tag.
override VERSION := $(shell awk -F'[<>]' '/<release>/{print $$3; exit}' $(MANIFEST))
CB_COMPAT := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' $(MANIFEST))
# `override`: dist_dev deletes $(INSTALL_DIR)/dev/$(ZIP_NAME) before building.
override ZIP_NAME := $(EXT_NAME)-v$(subst .,-,$(VERSION)).zip
# `override`: dist_release `rm -rf`s it, so it must not be settable on its own.
override RELEASE_STAGE := $(BUILD_DIR)/release
RELEASE_ROOT := $(RELEASE_STAGE)/$(PLUGIN_DIR)
RELEASE_ZIP := $(INSTALL_DIR)/release/$(ZIP_NAME)
# `override`: dist_dev deletes it before building. Move it with INSTALL_DIR instead.
override DEV_ZIP := $(INSTALL_DIR)/dev/$(ZIP_NAME)

# No Composer here: the plugin has no dependencies and no automated harness. These are the
# repository's own gates, and `release` runs them before it tags - so they are `:=`, not `?=`:
# an environment variable of the same name must not be able to replace them silently. `:=`
# expands immediately, so they must stay below the variables they reference.
DEPS_CMD := @echo "No dependencies to install."
TEST_CMD := echo "No automated test harness in this repository - release verification is manual."
# $(JZIP) is linted although it does not ship: it is the packager, and a syntax error in it
# would otherwise surface only after `release` has created the tag. The inventory check makes
# PACKAGE_FILES equal the files git tracks under $(PLUGIN_DIR): a file added there but not
# listed would otherwise be left out of the package with every other gate passing.
LINT_CMD := set -e; \
	dups="$$(printf '%s\n' $(PACKAGE_FILES) | sort | uniq -d)"; \
	if [ -n "$$dups" ]; then echo "FAIL: listed more than once in PACKAGE_FILES:" $$dups; exit 1; fi; \
	tracked="$$(git -c core.quotePath=false ls-files -- "$(PLUGIN_DIR)")" || { echo "FAIL: git ls-files failed - cannot check PACKAGE_FILES"; exit 1; }; \
	tracked=" $$(printf '%s' "$$tracked" | tr '\n' ' ') "; \
	unlisted=""; \
	for f in $$tracked; do \
		case " $(PACKAGE_FILES) " in *" $${f\#$(PLUGIN_DIR)/} "*) ;; *) unlisted="$$unlisted $${f\#$(PLUGIN_DIR)/}";; esac; \
	done; \
	if [ -n "$$unlisted" ]; then echo "FAIL: tracked under $(PLUGIN_DIR) but not in PACKAGE_FILES:$$unlisted"; exit 1; fi; \
	for f in $(PACKAGE_FILES); do \
		case "$$tracked" in *" $(PLUGIN_DIR)/$$f "*) ;; *) echo "FAIL: $$f is in PACKAGE_FILES but not tracked by git"; exit 1;; esac; \
	done; \
	for f in $(filter %.php,$(addprefix $(PLUGIN_DIR)/,$(PACKAGE_FILES))) $(JZIP); do out="$$(php -l "$$f" 2>&1)" || { printf '%s\n' "$$out"; exit 1; }; done; \
	php -r 'libxml_use_internal_errors(true); if (simplexml_load_file($$argv[1]) === false) { fwrite(STDERR, "FAIL: malformed XML in $$argv[1]\n"); exit(1); }' "$(MANIFEST)"; \
	echo "lint: ok"

# Every variable that decides the tag's checks or the published bytes.
# `release` and `dist_release` refuse any of them set from the command line or the
# environment (`?=` lets an exported variable through, and `make -e` lets one override `:=`).
RELEASE_LOCKED := ZIP_LEVEL JZIP PACKAGE_FILES EXT_NAME PLUGIN_DIR MANIFEST RELEASE_NOTES \
	RELEASE_ROOT RELEASE_ZIP TEST_CMD LINT_CMD sha256 package
release_overrides = $(strip $(foreach v,$(RELEASE_LOCKED),$(if $(filter command environment,$(firstword $(origin $(v)))),$(v))))

sha256 = $$( { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$(1)"; else shasum -a 256 "$(1)"; fi; } | awk '{print $$1}' | grep -Ex '[0-9a-f]{64}' )

# Package archive root $(2) into $(1), stamping every entry with epoch $(3).
define package
	@set -e; \
	for f in $(PACKAGE_FILES); do \
		if [ ! -f "$(2)/$$f" ]; then \
			echo "FAIL: $$f is in PACKAGE_FILES but not in $(2)/ - the staged tree is incomplete"; \
			exit 1; \
		fi; \
	done; \
	dest="$$(dirname "$(1)")"; \
	mkdir -p "$$dest"; \
	archive="$$(CDPATH= cd -- "$$dest" && pwd)/$$(basename "$(1)").part"; stamp="$(3)"; \
	rm -f "$(1).part"; \
	if [ -f "$(JZIP)" ]; then \
		php "$(JZIP)" --level=$(ZIP_LEVEL) "$$archive" "$$stamp" "$(2)" $(PACKAGE_FILES) >/dev/null; \
		how="jzip level $(ZIP_LEVEL)"; \
	else \
		echo "WARNING: jzip not found at $(JZIP) - falling back to zip." >&2; \
		echo "WARNING: the package is NOT reproducible - zip stores this machine's mtimes and host metadata." >&2; \
		( CDPATH= cd -- "$(2)" && zip -q -X -$(ZIP_LEVEL) "$$archive" $(PACKAGE_FILES) ); \
		how="zip fallback"; \
	fi; \
	mv "$(1).part" "$(1)"; \
	sum="$(call sha256,$(1))" || { rm -f "$(1)"; echo "FAIL: cannot compute the sha256 of $(1) - deleted it"; exit 1; }; \
	echo "$(1): $(words $(PACKAGE_FILES)) entries, $$(wc -c < "$(1)") bytes, $$how, sha256 $$sum"
endef

.PHONY: info deps test lint release dist_release dist_dev clean

info:
	@echo "Extension:       $(EXT_NAME)"
	@echo "Version:         $(VERSION)  (<release>)"
	@echo "CB compat:       $(CB_COMPAT)  (<version>)"
	@echo "Archive root:    $(PLUGIN_DIR)"
	@echo "Source:          $(PACKAGE_FILES)"
	@echo "Release package: $(RELEASE_ZIP)"
	@echo "Dev package:     $(DEV_ZIP)"
	@echo "Packager:        $(JZIP) (level $(ZIP_LEVEL))"

deps:
	$(DEPS_CMD)

test:
	@$(TEST_CMD)

lint:
	@$(LINT_CMD)

release:
	@# The locked variables decide the tag's notes check and, later, the published bytes;
	@# refuse them here too, so an override cannot get as far as creating the tag.
	@if [ -n "$(release_overrides)" ]; then \
		echo "FAIL: $(release_overrides) set from the command line or environment - unset them before tagging"; \
		exit 1; \
	fi
	@set -e; \
	dirty="$$(git status --porcelain --untracked-files=normal)" || { echo "FAIL: git status failed - cannot confirm the tree is clean"; exit 1; }; \
	if [ -n "$$dirty" ]; then \
		echo "FAIL: working tree is dirty - commit or stash before tagging"; \
		printf '%s\n' "$$dirty"; \
		exit 1; \
	fi
	@if [ -z "$(VERSION)" ]; then \
		echo "FAIL: no <release> in $(MANIFEST) - cannot derive the version"; \
		exit 1; \
	fi
	@if git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "FAIL: tag $(VERSION) already exists - bump <release> in $(MANIFEST)"; \
		exit 1; \
	fi
	@if ! awk -v v="$(VERSION)" '/^## /{ if ($$2==v) found=1 } END{ exit !found }' "$(RELEASE_NOTES)"; then \
		echo "FAIL: $(RELEASE_NOTES) has no '## $(VERSION)' heading - write the release notes before tagging"; \
		exit 1; \
	fi
	$(MAKE) test
	$(MAKE) lint
	@# -a -m: tag.gpgsign=true makes a bare `git tag` open an editor for the message, which
	@# aborts when make runs non-interactively. Signing itself still follows git config.
	git tag -a -m "Release $(VERSION)" "$(VERSION)"
	@echo "Tagged $(VERSION) - next: make dist_release"

dist_release:
	@if ! git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "FAIL: no tag $(VERSION) - run 'make release' first"; \
		exit 1; \
	fi
	@# Only the payload files come from the tag. PACKAGE_FILES, the packager, the
	@# and the compression level are all read from the checkout,
	@# so the archive is reproducible from the tag only when the checkout IS the tag.
	@set -e; \
	dirty="$$(git status --porcelain --untracked-files=normal)" || { echo "FAIL: git status failed - cannot confirm the tree is clean"; exit 1; }; \
	if [ -n "$$dirty" ]; then \
		echo "FAIL: working tree is dirty - a release must be built from a clean checkout of $(VERSION)"; \
		printf '%s\n' "$$dirty"; \
		exit 1; \
	fi; \
	head="$$(git rev-parse HEAD)"; \
	tagged="$$(git rev-parse "$(VERSION)^{commit}")"; \
	if [ "$$head" != "$$tagged" ]; then \
		echo "FAIL: HEAD is not tag $(VERSION)"; \
		echo "      HEAD $$head"; \
		echo "      tag  $$tagged"; \
		echo "      building here would use this checkout's build inputs against the tag's payload,"; \
		echo "      producing bytes a clean checkout of $(VERSION) cannot reproduce. Check the tag out."; \
		exit 1; \
	fi
	@# Same reasoning for the variables that decide the published bytes.
	@if [ -n "$(release_overrides)" ]; then \
		echo "FAIL: $(release_overrides) set from the command line or environment."; \
		echo "      These decide the published bytes and must come from the tagged"; \
		echo "      Makefile. Unset them, or change them in the repository instead."; \
		exit 1; \
	fi
	@# The package macro degrades to `zip` when the packager is missing. Tolerable for a
	@# dev package, never for a published one: its checksum could not be re-derived.
	@if [ ! -f "$(JZIP)" ]; then \
		echo "FAIL: $(JZIP) is missing - a release package must come from the deterministic"; \
		echo "      packager, never the zip fallback. Restore it before building."; \
		exit 1; \
	fi
	@# `release` linted before tagging, but a tag made by hand skipped it, and lint holds the
	@# PACKAGE_FILES inventory check. The checkout is the tag here, so this lints the tag.
	$(MAKE) lint
	@rm -f "$(RELEASE_ZIP)"
	@rm -rf "$(RELEASE_STAGE)"
	@mkdir -p "$(RELEASE_STAGE)"
	@git -c core.autocrlf=false archive "$(VERSION)" | tar -x -C "$(RELEASE_STAGE)"
	@set -e; \
	exported="$$(awk -F'[<>]' '/<release>/{print $$3; exit}' "$(RELEASE_STAGE)/$(MANIFEST)")"; \
	if [ "$$exported" != "$(VERSION)" ]; then \
		echo "FAIL: tag $(VERSION) exports a manifest declaring $$exported"; \
		echo "      the ZIP name comes from the WORKING TREE manifest,"; \
		echo "      so packaging this would publish one version under another's name - move the tag"; \
		echo "      or fix <release> before rebuilding."; \
		exit 1; \
	fi
	$(call package,$(RELEASE_ZIP),$(RELEASE_ROOT),$$(git log -1 --format=%ct "$(VERSION)"))
	@rm -rf "$(RELEASE_STAGE)"
	@echo "Upload $(RELEASE_ZIP) as the $(VERSION) GitHub release asset; put the sha256 above in the release description."

dist_dev:
	@# Nothing in the package is edited for a dev build, so jzip reads the working tree
	@# directly; PACKAGE_FILES keeps untracked files in the plugin directory out.
	@rm -f "$(DEV_ZIP)"
	$(call package,$(DEV_ZIP),$(PLUGIN_DIR),$$(git log -1 --format=%ct))

clean:
	@rm -rf "$(BUILD_DIR)" "$(INSTALL_DIR)/release" "$(INSTALL_DIR)/dev"
