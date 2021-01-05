const { series, src, dest } = require('gulp')
const composer = require('gulp-composer')
const clean = require('gulp-clean')
const zip = require('gulp-zip')
const tar = require('gulp-tar')
const gzip = require('gulp-gzip')
const exec = require('util').promisify(require('child_process').exec)
const eosp = require('end-of-stream-promise')
const cfg = require('./gulpfile.json')

/**
 * Perform composer update
 * @return stream
 */
async function doComposerUpdate() {
    try {
        await exec(`rm -rf servers/ispapissl/vendor`);
    } catch (e) {
    }
    await eosp(composer("update --no-dev"))
}

/**
 * Perform PHP Linting
 */
async function doLint() {
    // these may fail, it's fine
    try {
        await exec(`${cfg.phpcsfixcmd} ${cfg.phpcsparams}`);
    } catch (e) {
    }

    // these shouldn't fail
    try {
        await exec(`${cfg.phpcschkcmd} ${cfg.phpcsparams}`);
        await exec(`${cfg.phpcomptcmd} ${cfg.phpcsparams}`);
    } catch (e) {
        await Promise.reject(e.message);
    }
    await Promise.resolve();
}

/**
 * cleanup old build folder / archive
 * @return stream
 */
function doDistClean() {
    return src([cfg.archiveBuildPath, `${cfg.archiveFileName}-latest.zip`], { read: false, base: '.', allowEmpty: true })
        .pipe(clean({ force: true }))
}

/**
 * Copy all files/folders to build folder
 * @return stream
 */
function doCopyFiles() {
    return src(cfg.filesForArchive, { base: '.' })
        .pipe(dest(cfg.archiveBuildPath))
}

/**
 * Clean up files
 * @return stream
 */
function doFullClean() {
    return src(cfg.filesForCleanup, { read: false, base: '.', allowEmpty: true })
        .pipe(clean({ force: true }))
}

/**
 * build latest zip archive
 * @return stream
 */
function doGitZip() {
    return src(`./${cfg.archiveBuildPath}/**`)
        .pipe(zip(`${cfg.archiveFileName}-latest.zip`))
        .pipe(dest('.'))
}

/**
 * build zip archive
 * @return stream
 */
function doZip() {
    return src(`./${cfg.archiveBuildPath}/**`)
        .pipe(zip(`${cfg.archiveFileName}.zip`))
        .pipe(dest('./pkg'))
}

/**
 * build tar archive
 * @return stream
 */
function doTar() {
    return src(`./${cfg.archiveBuildPath}/**`)
        .pipe(tar(`${cfg.archiveFileName}.tar`))
        .pipe(gzip())
        .pipe(dest('./pkg'))
}

exports.prepare = series(
    doComposerUpdate,
    doLint,
    doDistClean,
    doCopyFiles
)
exports.default = series(
    exports.prepare,
    doGitZip,
    doZip,
    doTar,
    doFullClean
)
