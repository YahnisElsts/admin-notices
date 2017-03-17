<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Visual_Admin_Customizer
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

//Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the library being tested.
 */
function _manually_load_library() {
	require dirname( dirname( __FILE__ ) ) . '/AdminNotice.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_library' );

//Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

//Load our custom test case subclass.
require 'NoticeTestCase.php';