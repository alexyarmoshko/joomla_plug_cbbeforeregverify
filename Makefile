
PLUGIN_NAME := cbbeforeregverify
PLUGIN_DIR := components/com_comprofiler/plugin/user/plug_$(PLUGIN_NAME)
PLUGIN_MANIFEST_XML := $(PLUGIN_DIR)/$(PLUGIN_NAME).xml
PLUGIN_UPDATE_XML := plug_$(PLUGIN_NAME).update.xml
INSTALL_DIR := installation

CB_COMPAT_VERSION := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' $(PLUGIN_MANIFEST_XML))
PLUGIN_VERSION := $(shell awk -F'[<>]' '/<release>/{print $$3; exit}' $(PLUGIN_MANIFEST_XML))

ZIP_VERSION := $(subst .,-,$(PLUGIN_VERSION))
ZIP_NAME := plug_$(PLUGIN_NAME)-v$(ZIP_VERSION).zip
ZIP_PATH := $(INSTALL_DIR)/$(ZIP_NAME)

GITHUB_OWNER ?= alexyarmoshko
GITHUB_REPO ?= joomla_plug_cbbeforeregverify
GITHUB_REF ?= $(PLUGIN_VERSION)

.PHONY: dist info clean

info:
	@echo "Plugin:          $(PLUGIN_NAME)"
	@echo "Plugin version:  $(PLUGIN_VERSION)"
	@echo "CB compat:       $(CB_COMPAT_VERSION)"
	@echo "Source:          $(PLUGIN_DIR)"
	@echo "Package output:  $(ZIP_PATH)"
	@echo "Manifest output: $(PLUGIN_UPDATE_XML)"

dist: $(ZIP_PATH)
	@SHA256="$$( (command -v sha256sum >/dev/null && sha256sum "$(ZIP_PATH)" || shasum -a 256 "$(ZIP_PATH)") | awk '{print $$1}' )"; \
	DOWNLOAD_URL="https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/$(GITHUB_REF)/$(ZIP_NAME)"; \
	awk -v version="$(PLUGIN_VERSION)" -v url="$$DOWNLOAD_URL" -v sha="$$SHA256" '{ \
		if ($$0 ~ /<version>[^<]+<\/version>/) { \
			sub(/<version>[^<]+<\/version>/, "<version>" version "</version>"); \
		} else if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
			sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
		} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
			sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
		} \
		print; \
	}' "$(PLUGIN_UPDATE_XML)" > "$(PLUGIN_UPDATE_XML).tmp" && mv "$(PLUGIN_UPDATE_XML).tmp" "$(PLUGIN_UPDATE_XML)"
	@mkdir -p "$(INSTALL_DIR)"
	@echo "Updated $(PLUGIN_UPDATE_XML)"

$(ZIP_PATH):
	@mkdir -p "$(INSTALL_DIR)"
	@rm -f "$(ZIP_PATH)"
	@cd "$(PLUGIN_DIR)" && zip -qr -X "$(CURDIR)/$(ZIP_PATH)" . -x "*.DS_Store" -x "*/.DS_Store"
	@echo "Built $(ZIP_PATH)"
	
clean:
	@rm -f "$(ZIP_PATH)"
