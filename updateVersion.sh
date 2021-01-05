#!/bin/bash

# THIS SCRIPT UPDATES THE HARDCODED VERSION
# IT WILL BE EXECUTED IN STEP "prepare" OF
# semantic-release. SEE package.json

# version format: X.Y.Z
newversion="$1"
date="$(date +'%Y-%m-%d')"

printf -v sed_script 's/MODULEVersion\" => \"[0-9]+\.[0-9]+\.[0-9]+\"/MODULEVersion" => "%s"/g' "${newversion}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" servers/ispapissl/ispapissl.php
else
  sed -E -i -e "${sed_script}" servers/ispapissl/ispapissl.php
fi

printf -v sed_script 's/version\" => \"[0-9]+\.[0-9]+\.[0-9]+\"/version" => "%s"/g' "${newversion}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" addons/ispapissl_addon/ispapissl_addon.php
else
  sed -E -i -e "${sed_script}" addons/ispapissl_addon/ispapissl_addon.php
fi

printf -v sed_script 's/"ISPAPI SSL v[0-9]+\.[0-9]+\.[0-9]+"/"ISPAPI SSL v%s"/g' "${newversion}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" servers/ispapissl/whmcs.json
else
  sed -E -i -e "${sed_script}" servers/ispapissl/whmcs.json
fi

printf -v sed_script 's/"ISPAPI SSL Addon v[0-9]+\.[0-9]+\.[0-9]+"/"ISPAPI SSL Addon v%s"/g' "${newversion}"
if [[ "$OSTYPE" == "darwin"* ]]; then
  sed -E -i '' -e "${sed_script}" addons/ispapissl_addon/whmcs.json
else
  sed -E -i -e "${sed_script}" addons/ispapissl_addon/whmcs.json
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
