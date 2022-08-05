#!/bin/bash
cd /tmp || exit 1
wget -O whmcs.zip "https://www.whmcs.com/members/dl.php?type=d&id=1597"
unzip -q -o whmcs.zip
cd whmcs || exit 1
wget "https://github.com/rrpproxy/whmcs-rrpproxy-registrar/raw/master/whmcs-rrpproxy-registrar-latest.zip"
wget "https://github.com/hexonet/whmcs-ispapi-registrar/raw/master/whmcs-ispapi-registrar-latest.zip"
unzip -q -o whmcs-rrpproxy-registrar-latest.zip
unzip -q -o whmcs-ispapi-registrar-latest.zip
rm whmcs-*-registrar-latest.zip ../whmcs.zip
