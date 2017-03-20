<?php
namespace YeEasyAdminNotices\V1;

if (!class_exists(__NAMESPACE__ . '\\AdminNotice', false)) {

	class AdminNotice {
		const TYPE_SUCCESS = 'success';
		const TYPE_INFO = 'info';
		const TYPE_WARNING = 'warning';
		const TYPE_ERROR = 'error';

		const DISMISS_PER_USER = 'user';
		const DISMISS_PER_SITE = 'site';
		const DISMISS_ACTION_PREFIX = 'ye_v1_dismiss-';

		const DISMISSED_OPTION_PREFIX = 'ye_is_dismissed-';
		const DELAYED_NOTICE_OPTION = 'ye_delayed_notices';

		protected $id = null;
		protected $content = '';
		protected $noticeType = 'success';
		protected $customCssClasses = array();

		protected $allowedScreens = array();
		protected $requiredCapability = null;

		protected $isDismissible = false;
		protected $isPersistentlyDismissible = false;
		protected $dismissionScope = self::DISMISS_PER_SITE;

		public function __construct($id = null) {
			$this->id = $id;
		}

		/**
		 * Create a new notice.
		 *
		 * @param string $id
		 * @return AdminNotice
		 */
		public static function create($id = null) {
			return new static($id);
		}

		public function text($message) {
			$this->content = '<p>' . esc_html($message) . '</p>';
			return $this;
		}

		public function html($arbitraryHtml) {
			$this->content = '<p>' . $arbitraryHtml . '</p>';
			return $this;
		}

		public function rawHtml($arbitraryHtml) {
			$this->content = $arbitraryHtml;
			return $this;
		}

		public function getHtmlContent() {
			return $this->content;
		}

		public function type($noticeType) {
			$this->noticeType = $noticeType;
			return $this;
		}

		public function success($messageHtml = null) {
			return $this->setTypeAndMessage(self::TYPE_SUCCESS, $messageHtml);
		}

		public function info($messageHtml = null) {
			return $this->setTypeAndMessage(self::TYPE_INFO, $messageHtml);
		}

		public function warning($messageHtml = null) {
			return $this->setTypeAndMessage(self::TYPE_WARNING, $messageHtml);
		}

		public function error($messageHtml = null) {
			return $this->setTypeAndMessage(self::TYPE_ERROR, $messageHtml);
		}

		protected function setTypeAndMessage($noticeType, $messageHtml = null) {
			$this->noticeType = $noticeType;
			if (isset($messageHtml)) {
				$this->html($messageHtml);
			}
			return $this;
		}

		public function addClass($className) {
			if (is_array($className)) {
				$className = implode(' ', $className);
			}
			$this->customCssClasses[] = $className;
			return $this;
		}

		/**
		 * Make the notice dismissible.
		 *
		 * @return $this
		 */
		public function dismissible() {
			$this->isDismissible = true;
			return $this;
		}

		/**
		 * When the user dismisses the notice, remember that and don't show it again.
		 *
		 * @param string $scope
		 * @return $this
		 */
		public function persistentlyDismissible($scope = self::DISMISS_PER_SITE) {
			if (empty($this->id)) {
				throw new \LogicException('Persistently dismissible notices must have a unique ID.');
			}

			$this->isDismissible = true;
			$this->isPersistentlyDismissible = true;
			$this->dismissionScope = $scope;

			$ajaxCallback = array($this, 'ajaxDismiss');
			if (has_action($this->getDismissActionName(), $ajaxCallback) === false) {
				add_action('wp_ajax_' . $this->getDismissActionName(), $ajaxCallback);
			}

			return $this;
		}

		/**
		 * Only show the notice on the specified admin page(s).
		 *
		 * @link https://codex.wordpress.org/Plugin_API/Admin_Screen_Reference
		 *
		 * @param string|string[] $screenId
		 * @return $this
		 */
		public function onPage($screenId) {
			$this->allowedScreens = array_merge($this->allowedScreens, (array)$screenId);
			return $this;
		}

		/**
		 * Get the current screen ID.
		 *
		 * @return null|string
		 */
		private function getCurrentScreenId() {
			if (!function_exists('get_current_screen')) {
				return null;
			}

			$screen = \get_current_screen();
			if ($screen === null) {
				return null;
			}
			return $screen->id;
		}

		/**
		 * Only show this notice to users that have the specified capability.
		 *
		 * @param string|null $capability
		 * @return $this
		 */
		public function requiredCap($capability) {
			$this->requiredCapability = $capability;
			return $this;
		}

		/**
		 * Show the notice on the current page when all preconditions are met.
		 */
		public function show() {
			if (did_action('admin_notices')) {
				$this->maybeOutputNotice();
			} else {
				add_action('admin_notices', array($this, 'maybeOutputNotice'));
			}
			return $this;
		}

		/**
		 * Immediately output the notice unless it has been dismissed.
		 *
		 * @internal
		 */
		public function maybeOutputNotice() {
			if (isset($this->requiredCapability) && !current_user_can($this->requiredCapability)) {
				return;
			}

			if (!empty($this->allowedScreens) && !in_array($this->getCurrentScreenId(), $this->allowedScreens)) {
				return;
			}

			if ($this->isDismissed()) {
				return;
			}
			$this->outputNotice();
		}

		/**
		 * Output the notice.
		 */
		public function outputNotice() {
			$classes = array_merge(
				array('notice', 'notice-' . $this->noticeType),
				$this->customCssClasses
			);

			if ($this->isDismissible) {
				$classes[] = 'is-dismissible';
			}

			$attributes = array(
				'id' => $this->id,
				'class' => implode(' ', $classes),
			);

			if ($this->isPersistentlyDismissible) {
				$attributes['data-ye-dismiss-nonce'] = wp_create_nonce($this->getDismissActionName());

				$attributes['data-ye-notice-data'] = $this->toJson();
				$attributes['data-ye-signature'] = wp_create_nonce(
					$this->id . '|' . $attributes['data-ye-dismiss-nonce'] . '|' . $attributes['data-ye-notice-data']
				);

				$this->enqueueScriptOnce();
			}

			/** @noinspection HtmlUnknownAttribute */
			printf(
				'<div %1$s>%2$s</div>',
				$this->formatTagAttributes($attributes),
				$this->content
			);
		}

		protected function enqueueScriptOnce() {
			if (!wp_script_is('ye-dismiss-notice', 'registered')) {
				//Note: Queueing a script also registers it.
				wp_enqueue_script(
					'ye-dismiss-notice',
					plugins_url('dismiss-notice.js', __FILE__),
					array('jquery'),
					'20170318',
					true
				);
			}
		}

		protected function formatTagAttributes($attributes) {
			$attributePairs = array();
			foreach ($attributes as $name => $value) {
				if (isset($value)) {
					$attributePairs[] = $name . '="' . esc_attr($value) . '"';
				}
			}
			return implode(' ', $attributePairs);
		}

		/**
		 * Show the notice on the next admin page that's visited by the current user.
		 * The notice will be shown only once.
		 *
		 * More accurately, this shows the notice the next time the admin_notices hook is called
		 * in the context of the current user, whether that happens during this page load or the next,
		 * or a week later. The intended use is for form handlers that redirect to another page, plugin
		 * activation hooks and other callbacks that can't display a notice in the usual way.
		 *
		 * @return self
		 */
		public function showOnNextPage() {
			if (!is_user_logged_in()) {
				return $this;
			}

			//Schedule the notice to appear on the next page.
			add_user_meta(
				get_current_user_id(),
				static::DELAYED_NOTICE_OPTION,
				wp_slash($this->toJson()),
				false
			);

			return $this;
		}

		/**
		 * Display delayed notices stored by showOnNextPage.
		 *
		 * @internal
		 */
		public static function _showDelayedNotices() {
			$userId = get_current_user_id();
			$notices = get_user_meta($userId, static::DELAYED_NOTICE_OPTION, false);
			if (empty($notices)) {
				return;
			}

			foreach ($notices as $json) {
				$notice = static::tryUnserializeNotice($json);
				if (isset($notice)) {
					$notice->show();

					//Only show the notice once.
					delete_user_meta($userId, static::DELAYED_NOTICE_OPTION, wp_slash($json));
				}
			}
		}

		/**
		 * Attempt to unserialize a notice from JSON.
		 *
		 * @internal
		 * @param string $json
		 * @return null|static
		 */
		protected static function tryUnserializeNotice($json) {
			$properties = json_decode($json, true);
			if (!is_array($properties)) {
				return null;
			}

			//Ignore notices created by other versions of this class.
			if (self::getKey($properties, '_className') !== get_called_class()) {
				return null;
			}

			return static::fromJson($json);
		}

		/**
		 * Serialize the notice as JSON.
		 *
		 * @return string
		 */
		public function toJson() {
			$data = array(
				'id'                        => $this->id,
				'content'                   => $this->content,
				'noticeType'                => $this->noticeType,
				'isDismissible'             => $this->isDismissible,
				'isPersistentlyDismissible' => $this->isPersistentlyDismissible,
				'dismissionScope'           => $this->dismissionScope,
				'customCssClasses'          => $this->customCssClasses,
				'allowedScreens'            => $this->allowedScreens,
				'requiredCapability'        => $this->requiredCapability,
				'_className'                => get_class($this),
			);

			return json_encode($data);
		}

		/**
		 * Load a notice from JSON.
		 *
		 * @param string $json
		 * @return AdminNotice
		 */
		public static function fromJson($json) {
			$properties = json_decode($json, true);

			$notice = new static($properties['id']);
			$notice->rawHtml($properties['content']);
			$notice->type($properties['noticeType']);
			$notice->addClass($properties['customCssClasses']);
			$notice->onPage(self::getKey($properties, 'allowedScreens', array()));
			$notice->requiredCap(self::getKey($properties, 'requiredCapability'));

			if ($properties['isDismissible']) {
				$notice->dismissible();
			}
			if ($properties['isPersistentlyDismissible']) {
				$notice->persistentlyDismissible(self::getKey($properties, 'dismissionScope', self::DISMISS_PER_SITE));
			}

			return $notice;
		}

		/**
		 * @param array $array
		 * @param string $key
		 * @param mixed $defaultValue
		 * @return mixed
		 */
		protected static function getKey($array, $key, $defaultValue = null) {
			if (array_key_exists($key, $array)) {
				return $array[$key];
			}
			return $defaultValue;
		}

		/**
		 * Process an AJAX request to dismiss this notice.
		 *
		 * @internal
		 */
		public function ajaxDismiss() {
			check_ajax_referer($this->getDismissActionName());

			if (!is_user_logged_in()) {
				wp_die('Access denied. You need to be logged in to dismiss notices.');
				return;
			}

			if (isset($this->requiredCapability) && !current_user_can($this->requiredCapability)) {
				wp_die('Access denied. You don\'t have the required capability to dismiss this notice.');
				return;
			}

			$this->dismiss();
			exit('Notice dismissed');
		}

		public function dismiss() {
			if (!$this->isPersistentlyDismissible) {
				return $this;
			}

			if ($this->dismissionScope === self::DISMISS_PER_SITE) {
				update_option($this->getDismissOptionName(), true);
			} else {
				update_user_meta(get_current_user_id(), $this->getDismissOptionName(), true);
			}

			return $this;
		}

		public function undismiss($scope = self::DISMISS_PER_SITE) {
			if (!$this->isPersistentlyDismissible) {
				return $this;
			}

			if ($this->dismissionScope === self::DISMISS_PER_SITE) {
				delete_option($this->getDismissOptionName());
			} else {
				if ($scope === self::DISMISS_PER_SITE) {
					//Un-dismiss it for all users.
					delete_metadata('user', 0, $this->getDismissOptionName(), '', true);
				} else {
					//Un-dismiss just for the current user.
					delete_user_meta(get_current_user_id(), $this->getDismissOptionName());
				}
			}

			return $this;
		}

		/**
		 * Delete all "dismissed" flags that have the specified prefix.
		 *
		 * @param string $prefix
		 */
		public static function cleanUpDatabase($prefix) {
			global $wpdb;
			/** @var \wpdb $wpdb */
			$escapedPrefix = esc_sql($wpdb->esc_like(static::DISMISSED_OPTION_PREFIX . $prefix) . '%');

			if (!is_string($escapedPrefix) || (strlen($escapedPrefix) < 2)) {
				throw new \LogicException('Prefix must not be empty.'); //This should never happen.
			}

			$wpdb->query(sprintf(
				'DELETE FROM %s WHERE option_name LIKE "%s"',
				$wpdb->options,
				$escapedPrefix
			));
			$wpdb->query(sprintf(
				'DELETE FROM %s WHERE meta_key LIKE "%s"',
				$wpdb->usermeta,
				$escapedPrefix
			));
		}

		public function isDismissed() {
			if (!$this->isPersistentlyDismissible) {
				return false;
			}

			if ($this->dismissionScope === self::DISMISS_PER_SITE) {
				return (boolean)(get_option($this->getDismissOptionName(), false));
			} else {
				return (boolean)(get_user_meta(get_current_user_id(), $this->getDismissOptionName(), true));
			}
		}

		protected function getDismissActionName() {
			return self::DISMISS_ACTION_PREFIX . $this->id;
		}

		protected function getDismissOptionName() {
			return static::DISMISSED_OPTION_PREFIX . $this->id;
		}

		/**
		 * @internal
		 */
		public static function _ajaxAddDismissalHandler() {
			if (!self::isUnhandledAjaxAction()) {
				return;
			}

			$id =        substr($_POST['action'], strlen(self::DISMISS_ACTION_PREFIX));
			$ajaxNonce = strval($_POST['_ajax_nonce']);
			$json =      strval(wp_unslash($_POST['notice-data']));
			if (!wp_verify_nonce($_POST['signature'], $id . '|' . $ajaxNonce . '|' . $json)) {
				return;
			}

			//The notice will automatically set an AJAX hook when it gets unserialized.
			self::tryUnserializeNotice($json);
		}

		/**
		 * Is this a "dismiss notice" AJAX request without a registered action callback?
		 *
		 * @internal
		 * @return bool
		 */
		protected static function isUnhandledAjaxAction() {
			$doingAjax = defined('DOING_AJAX') && constant('DOING_AJAX');
			if (!$doingAjax) {
				return false;
			}

			$requiredParams = array('action', 'notice-data', 'signature', '_ajax_nonce');
			foreach($requiredParams as $param) {
				if (empty($_POST[$param]) || !is_string($_POST[$param])) {
					return false;
				}
			}

			$action = $_POST['action'];
			if (has_action('wp_ajax_' . $action) === true) {
				return false;
			}

			$isDismissAction = self::stringStartsWith($action, self::DISMISS_ACTION_PREFIX)
				&& (strlen($action) > strlen(self::DISMISS_ACTION_PREFIX));
			return $isDismissAction;
		}

		protected static function stringStartsWith($input, $prefix) {
			return substr($input, 0, strlen($prefix)) === $prefix;
		}
	}

	/**
	 * Create an admin notice.
	 *
	 * @param string $id
	 * @return AdminNotice
	 */
	function easyAdminNotice($id = null) {
		return new AdminNotice($id);
	}

	add_action('admin_notices', array(__NAMESPACE__ . '\\AdminNotice', '_showDelayedNotices'));
	add_action('admin_init', array(__NAMESPACE__ . '\\AdminNotice', '_ajaxAddDismissalHandler'), 300);

} //class_exists