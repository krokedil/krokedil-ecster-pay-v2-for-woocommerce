/* globals require */
var argv     = require('yargs').argv;
var gulp     = require('gulp');
var watch    = require('gulp-watch');
var wpPot    = require('gulp-wp-pot');
var sort     = require('gulp-sort');
var uglify   = require('gulp-uglify');
var rename   = require('gulp-rename');
var pump     = require('pump');
var cleanCSS = require('gulp-clean-css');
var zip      = require('gulp-zip');

gulp.task('makePOT', function () {
	return gulp.src('**/*.php')
		.pipe(sort())
		.pipe(wpPot({
			domain: 'krokedil-ecster-pay-for-woocommerce',
			destFile: 'languages/krokedil-ecster-pay-for-woocommerce.pot',
			package: 'krokedil-ecster-pay-for-woocommerce',
			bugReport: 'http://krokedil.se',
			lastTranslator: 'Slobodan Manic <slobodan@krokedil.se>',
			team: 'Krokedil <info@krokedil.se>'
		}))
		.pipe(gulp.dest('.'));
});

gulp.task('createZIP', function () {
	var version = argv.version ? argv.version : '';
	return gulp.src([
		'assets/**/*',
		'includes/**/*',
		'languages/**/*',
		'views/**/*',
		'krokedil-ecster-pay-for-woocommerce.php',
		'readme.txt',
		'changelog.txt'
	], {"base": "."})
		.pipe(zip('krokedil-ecster-pay-for-woocommerce.zip'))
		.pipe(gulp.dest('build/' + version));
});

gulp.task('compressJS', function (cb) {
	pump([
			gulp.src('assets/js/frontend/checkout.js'),
			uglify(),
			rename({suffix: '.min'}),
			gulp.dest('assets/js/frontend')
		],
		cb
	);
});

gulp.task('compressCSS', function () {
	return gulp.src('assets/css/frontend/checkout.css')
		.pipe(cleanCSS({debug: true}))
		.pipe(rename({suffix: '.min'}))
		.pipe(gulp.dest('assets/css/frontend'));
});

gulp.task('watch', function () {
	gulp.watch('assets/css/frontend/checkout.css', ['compressCSS']);
	gulp.watch('assets/js/frontend/checkout.js', ['compressJS']);
});

gulp.task('build', ['makePOT', 'compressJS', 'compressCSS', 'createZIP']);