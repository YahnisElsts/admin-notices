<?php
namespace YeEasyAdminNotices\V1;
use DOMDocument, DOMXPath;

class NoticeTestCase extends \WP_UnitTestCase {
	/**
	 * @return DOMXPath
	 */
	protected function doAdminNotices() {
		ob_start();
		do_action('admin_notices');
		$output = ob_get_contents();
		ob_end_clean();

		$dom = new DOMDocument();
		if ($output !== '') {
			$dom->loadHTML($output);
		}
		return new DOMXPath($dom);
	}

	protected function assertNoticeCount(DOMXPath $output, $expectedNumberOfNotices, $message = '') {
		$notices = $this->getNotices($output);
		$this->assertEquals($expectedNumberOfNotices, $notices->length, $message);
	}

	protected function getNotices(DOMXPath $xpath) {
		$noticeQuery = ".//div[contains(concat(' ', normalize-space(@class), ' '), ' notice ')]";
		return $xpath->query($noticeQuery);
	}

	protected function assertNoticeHasClass(DOMXPath $output, $className, $message = '', $index = 0) {
		$results = $this->getNotices($output);
		$notice = $results->item($index);

		if ($notice === null) {
			$this->assertNotNull($notice, $message);
			return;
		}

		$classes = explode(' ', $notice->getAttribute('class'));
		$this->assertContains($className, $classes, $message);
	}

	protected function assertNoticeTextEquals(DOMXPath $output, $expectedText, $message = '', $index = 0) {
		$results = $this->getNotices($output);
		$notice = $results->item($index);

		if ($notice === null) {
			$this->assertNotNull($notice, $message);
			return;
		}

		//Notice text is usually wrapped in <p>...</p> tags, but plugins can override that.
		$paragraphs = $notice->getElementsByTagName('p');
		if ($paragraphs->length === 1) {
			$actualText = $paragraphs->item(0)->textContent;
		} else {
			$actualText = $notice->textContent;
		}

		$this->assertEquals($expectedText, $actualText, $message);
	}
}