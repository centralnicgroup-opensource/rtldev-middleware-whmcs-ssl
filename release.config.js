const basecfg = require('@hexonet/semantic-release-github-whmcs-config');

basecfg.plugins = basecfg.plugins.map(function (val, index) {
    const plugin = basecfg.plugins[index];
    if (Array.isArray(plugin) && plugin[0] === "@semantic-release/exec") {
        plugin[1] = {
            "prepareCmd": "./updateVersion.sh ${nextRelease.version} && gulp release"
        };
    }
    return plugin;
});

module.exports = basecfg;