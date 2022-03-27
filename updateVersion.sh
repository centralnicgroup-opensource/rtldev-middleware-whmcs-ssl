#!/bin/bash

# THIS SCRIPT UPDATES THE HARDCODED VERSION
# IT WILL BE EXECUTED IN STEP "prepare" OF
# semantic-release. SEE package.json

# version format: X.Y.Z
newversion="$1"
date="$(date +'%Y-%m-%d')"

printf -v sed_script 's/MODULEVersion\" => \"[0-9]+\.[0-9]+\.[0-9]+\"/MODULEVersion" => "%s"/g' "${newversion}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" modules/servers/cnicssl/cnicssl.php
else
  sed -E -i -e "${sed_script}" modules/servers/cnicssl/cnicssl.php
fi

printf -v sed_script 's/version\" => \"[0-9]+\.[0-9]+\.[0-9]+\"/version" => "%s"/g' "${newversion}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" modules/addons/cnicssl_addon/cnicssl_addon.php
else
  sed -E -i -e "${sed_script}" modules/addons/cnicssl_addon/cnicssl_addon.php
fi

printf -v sed_script 's/"CNIC SSL v[0-9]+\.[0-9]+\.[0-9]+"/"CNIC SSL v%s"/g' "${newversion}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" modules/servers/cnicssl/whmcs.json
else
  sed -E -i -e "${sed_script}" modules/servers/cnicssl/whmcs.json
fi

printf -v sed_script 's/"CNIC SSL Addon v[0-9]+\.[0-9]+\.[0-9]+"/"CNIC SSL Addon v%s"/g' "${newversion}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" modules/addons/cnicssl_addon/whmcs.json
else
  sed -E -i -e "${sed_script}" modules/addons/cnicssl_addon/whmcs.json
fi

printf -v sed_script 's/"version": "[0-9]+\.[0-9]+\.[0-9]+"/"version": "%s"/g' "${newversion}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" release.json
else
  sed -E -i -e "${sed_script}" release.json
fi

printf -v sed_script 's/"date": "[0-9]{4}-[0-9]{2}-[0-9]{2}"/"date": "%s"/g' "${date}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" release.json
else
  sed -E -i -e "${sed_script}" release.json
fi
