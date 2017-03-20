<?php
use YeEasyAdminNotices\V1\AdminNotice;
use YeEasyAdminNotices\V1\NoticeTestCase;

class BasicNoticeTest extends NoticeTestCase {

	/**
	 * The most basic test.
	 */
	function testBasicNotice() {
		$notice = AdminNotice::create('sample')
			->text('Hello world!')
			->show();
			
		$this->assertTrue(
			has_action('admin_notices', array($notice, 'maybeOutputNotice')) > 0,
			'AdminNotice adds a hook to admin_notices'
		);

		$output = $this->doAdminNotices();
		$this->assertNoticeCount($output, 1);
		$this->assertNoticeTextEquals($output, 'Hello world!');
		$this->assertNoticeHasClass($output, 'notice-success');
	}

	function testTextAddsParagraph() {
		$notice = AdminNotice::create()->text('example');
		$this->assertEquals('<p>example</p>', $notice->getHtmlContent());
	}

	function testTextEscapesSpecialCharacters() {
		$notice = AdminNotice::create()->text('<b>"Foo &co"</b>');
		$this->assertEquals('<p>&lt;b&gt;&quot;Foo &amp;co&quot;&lt;/b&gt;</p>', $notice->getHtmlContent());
	}

	function testHtmlDoesNotEscapeInput() {
		$notice = AdminNotice::create()->html('<b>"Foo &co"</b>');
		$this->assertEquals('<p><b>"Foo &co"</b></p>', $notice->getHtmlContent());
	}

	function testSuccessHelperAddsClass() {
		AdminNotice::create()->success('Successful notice')->show();
		$output = $this->doAdminNotices();
		$this->assertNoticeHasClass($output, 'notice-success');
	}

	function testInfoHelperAddsClass() {
		AdminNotice::create()->info('Information notice')->show();
		$output = $this->doAdminNotices();
		$this->assertNoticeHasClass($output, 'notice-info');
	}

	function testWarningHelperAddsClass() {
		AdminNotice::create()->warning('Warning notice')->show();
		$output = $this->doAdminNotices();
		$this->assertNoticeHasClass($output, 'notice-warning');
	}

	function testErrorHelperAddsClass() {
		AdminNotice::create()->error('Error notice')->show();
		$output = $this->doAdminNotices();
		$this->assertNoticeHasClass($output, 'notice-error');
	}

	function testDismissibleNoticeHasClass() {
		AdminNotice::create()->success('You can dismiss me')->dismissible()->show();
		$this->assertNoticeHasClass($this->doAdminNotices(), 'is-dismissible');
	}

	function testDismissedNoticeRemembersState() {
		$notice = AdminNotice::create('test1')->success('You can dismiss me')->persistentlyDismissible();
		$notice->dismiss();
		$this->assertTrue($notice->isDismissed());
	}

	function testDismissedNoticeStopsShowingUp() {
		$notice = AdminNotice::create('test2')->success('You can dismiss me')->persistentlyDismissible()->show();

		//Show the notice once.
		$this->assertNoticeCount($this->doAdminNotices(), 1);

		//Then dismiss it.
		$notice->dismiss();

		//It shouldn't show up again.
		$this->assertNoticeCount($this->doAdminNotices(), 0);
	}

	function testYouCanCreateMultipleNotices() {
		AdminNotice::create()->success('Good news')->show();
		AdminNotice::create()->error('Bad news')->show();

		$output = $this->doAdminNotices();

		$this->assertNoticeCount($output, 2);
		$this->assertNoticeTextEquals($output, 'Good news', '', 0);
		$this->assertNoticeTextEquals($output, 'Bad news', '', 1);
	}

	/**
	 * @expectedException LogicException
	 */
	function testDismissibleNoticesMustHaveId() {
		AdminNotice::create()->persistentlyDismissible();
	}

	function testDatabaseCleanupIsSafe() {
		global $wpdb;

		//Add a dummy user to give the dismiss-per-user feature something to work with.
		wp_set_current_user($this->factory->user->create());

		//Get the initial number of rows in the DB tables that are used to store "dismissed" flags.
		$initialOptionCount = intval($wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->options));
		$initialMetaCount = intval($wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->usermeta));

		$this->assertTrue($initialOptionCount > 0);
		$this->assertTrue($initialMetaCount > 0);

		//Add a few dismissible notices and then dismiss them, which will store flags in the database.
		AdminNotice::create('test-1')
			->persistentlyDismissible(AdminNotice::DISMISS_PER_SITE)
			->dismiss();
		AdminNotice::create('test-2')
			->persistentlyDismissible(AdminNotice::DISMISS_PER_SITE)
			->dismiss();

		AdminNotice::create('test-user-1')
			->persistentlyDismissible(AdminNotice::DISMISS_PER_USER)
			->dismiss();
		AdminNotice::create('test-user-2')
			->persistentlyDismissible(AdminNotice::DISMISS_PER_USER)
			->dismiss();

		//Clean up all dismission flags.
		AdminNotice::cleanUpDatabase('test');

		//The number of rows should be exactly the same as in the beginning.
		//The cleanup method shouldn't leave anything behind, and it definitely shouldn't delete
		//anything more than the flags that were created by this library.
		$newOptionCount = intval($wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->options));
		$newMetaCount = intval($wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->usermeta));

		$this->assertEquals($initialOptionCount, $newOptionCount);
		$this->assertEquals($initialMetaCount, $newMetaCount);
	}

	function testRequiredCapRestrictsAccess() {
		AdminNotice::create()
			->text('Only users with the "manage_options" cap will see this')
			->requiredCap('manage_options')
			->show();

		$administrator = $this->factory->user->create(array('role' => 'administrator'));
		$author = $this->factory->user->create(array('role' => 'author'));

		wp_set_current_user($administrator);
		$this->assertNoticeCount(
			$this->doAdminNotices(),
			1,
			"User with the required capability can see the notice"
		);

		wp_set_current_user($author);
		$this->assertNoticeCount(
			$this->doAdminNotices(),
			0,
			"User without the required capability can't see the notice"
		);
	}

	function testNoticesAppearEverywhereByDefault() {
		//By default, notices appear on all admin pages.
		AdminNotice::create()->text('A notice')->show();

		set_current_screen('options-general');
		$this->assertNoticeCount($this->doAdminNotices(), 1);
		set_current_screen('dashboard');
		$this->assertNoticeCount($this->doAdminNotices(), 1);
		set_current_screen('edit-post');
		$this->assertNoticeCount($this->doAdminNotices(), 1);
	}

	function testOnPageRestrictsWhereNoticeAppears() {
		//This notice only appears on "Settings".
		AdminNotice::create()->onPage('options-general')->text('Show me')->show();

		set_current_screen('options-general');
		$this->assertNoticeCount($this->doAdminNotices(), 1);

		set_current_screen('dashboard');
		$this->assertNoticeCount($this->doAdminNotices(), 0);
	}
}
