ISPAPI_SSL_MODULE_VERSION := $(shell php -r 'include "servers/ispapissl/ispapissl.php"; print $$module_version;')
FOLDER := pkg/whmcs-ispapi-ssl-$(ISPAPI_SSL_MODULE_VERSION)

clean:
	rm -rf $(FOLDER)

buildsources:
	mkdir -p $(FOLDER)/install/modules/servers
	mkdir -p $(FOLDER)/install/modules/addons
	cp -a servers/ispapissl $(FOLDER)/install/modules/servers
	cp -a addons/ispapissl_addon $(FOLDER)/install/modules/addons
	cp README.md HISTORY.md HISTORY.old CONTRIBUTING.md LICENSE README.pdf $(FOLDER)
	find $(FOLDER)/install -name "*~" | xargs rm -f
	find $(FOLDER)/install -name "*.bak" | xargs rm -f

buildlatestzip:
	cp pkg/whmcs-ispapi-ssl.zip ./whmcs-ispapi-ssl-latest.zip # for downloadable "latest" zip by url

zip:
	rm -rf pkg/whmcs-ispapi-ssl.zip
	@$(MAKE) buildsources
	cd pkg && zip -r whmcs-ispapi-ssl.zip whmcs-ispapi-ssl-$(ISPAPI_SSL_MODULE_VERSION)
	@$(MAKE) clean

tar:
	rm -rf pkg/whmcs-ispapi-ssl.tar.gz
	@$(MAKE) buildsources
	cd pkg && tar -zcvf whmcs-ispapi-ssl.tar.gz whmcs-ispapi-ssl-$(ISPAPI_SSL_MODULE_VERSION)
	@$(MAKE) clean

allarchives:
	rm -rf pkg/whmcs-ispapi-ssl.zip
	rm -rf pkg/whmcs-ispapi-ssl.tar
	@$(MAKE) buildsources
	cd pkg && zip -r whmcs-ispapi-ssl.zip whmcs-ispapi-ssl-$(ISPAPI_SSL_MODULE_VERSION) && tar -zcvf whmcs-ispapi-ssl.tar.gz whmcs-ispapi-ssl-$(ISPAPI_SSL_MODULE_VERSION)
	@$(MAKE) buildlatestzip
	@$(MAKE) clean
