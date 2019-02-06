# Easy Admin Notices
A PHP utility library for WordPress plugins that helps create admin notices.

[![Build Status](https://travis-ci.org/YahnisElsts/admin-notices.svg?branch=master)](https://travis-ci.org/YahnisElsts/admin-notices)

## Highlights
* Fluent interface.
* [Persistently dismissible notices](#persistentlydismissiblescope) with optional dismissal duration.
* Show notices [on specific pages](#onpagescreenid).
* Show notices to users who have [a specific capability](#requiredcapcapability).

## Requirements

- PHP 5.3 or later.
- WordPress 4.2 or later.

## Installation

Install with Composer:
```
composer require "yahnis-elsts/admin-notices"
```

Alternatively, you can install it manually:
1. Download [the latest release](https://github.com/YahnisElsts/admin-notices/releases/latest).
2. Move the `admin-notices` directory to your plugin.
3. Load the library: 
	```php 
	require '/path/to/admin-notices/AdminNotice.php';
	```

A note on load order: To ensure that persistently dismissible notices will work correctly, you should `require` the library *before* the `admin_init` action. For example, you can put the `require` in a `plugins_loaded` hook, or simply at the top of your plugin.  

## Usage

Basic example:
```php
use \YeEasyAdminNotices\V1\AdminNotice;

AdminNotice::create()
	->success('Hello world!')
	->show();
```

Result:

![Basic admin notice](https://cloud.githubusercontent.com/assets/2527434/24096796/c5d87d28-0d6b-11e7-82ad-519a8b24ece8.png)

You don't have to put this code in a hook. The `show()` method is smart. It checks if the `admin_notices` action was already executed and either displays the notice immediately or adds a new `admin_notices` hook that will display the notice when appropriate. It also checks any capability requirements and page filters before actually showing the notice (see [Preconditions](#preconditions)).

### Type shortcuts

WordPress comes with several built-in CSS classes for different types of admin notices. You can use the following shortcut methods to simultaneously set the notice type and the contents of the notice.

##### `success([$message])`
```php
AdminNotice::create()->success('Success')->show();
```
![Success notice](https://cloud.githubusercontent.com/assets/2527434/24096800/c5ee4ebe-0d6b-11e7-9956-91a73e656539.png)

##### `error([$message])`
```php 
AdminNotice::create()->error('Error')->show(); 
```
![Error notice](https://cloud.githubusercontent.com/assets/2527434/24096799/c5dd524e-0d6b-11e7-9fb8-d9164821ed43.png)

##### `warning([$message])`
```php
AdminNotice::create()->warning('Warning')->show();
```
![Warning notice](https://cloud.githubusercontent.com/assets/2527434/24096802/c5f77174-0d6b-11e7-8190-101c6405f478.png)

##### `info([$message])`
```php
AdminNotice::create()->info('Information')->show();
```
![Informational notice](https://cloud.githubusercontent.com/assets/2527434/24096803/c6427d40-0d6b-11e7-9bb2-0f9d28a3df1a.png)

### Content

Instead of passing a string to one of the type-specific methods, you can set the contents of the notice by calling one of the following methods. 

##### `text($message)`

Set the contents of the notice to a text string. `text()` will escape 
HTML special characters like `<`, `>`, `&` and so on.

Example:
```php
AdminNotice::create()
	->info()
	->text('<script>/* This will be displayed as plain text. */</script>')
	->show();
```

![Text escaping](https://cloud.githubusercontent.com/assets/2527434/24096797/c5d935c4-0d6b-11e7-81a6-e94c88a53096.png)

##### `html($arbitraryHtml)`

Set the contents of the notice to a HTML string. Unlike `text()`, this method does not perform any escaping or encoding.

```php
AdminNotice::create()
	->info()
	->html('Tip: Go to <a href="#">Settings -&gt; My Plugin</a> to configure the plugin.')
	->show();
```

![HTML content](https://cloud.githubusercontent.com/assets/2527434/24096798/c5dbf0de-0d6b-11e7-9c5a-893ef7d568e0.png)
 
##### `rawHtml($arbitraryHtml)`

Usually, the contents of a notice are wrapped in a single paragraph (`<p>`) tag. To prevent this wrapping, use `rawHtml()` to set the notice content. This is useful if you want to use complex HTML or to display a long message where one paragraph is not enough.

```php
AdminNotice::create()
	->rawHtml('<p>First paragraph</p><p>Second paragraph</p>')
	->show();
```

![Raw HTML content](https://cloud.githubusercontent.com/assets/2527434/24096794/c5af9ade-0d6b-11e7-9e96-9b5e174dbaa0.png)

### Dismissible notices

##### `dismissible()`

Add an "(x)" icon to the notice. Clicking the icon will hide the notice. However, this doesn't prevent the notice from reappearing in the future. Use `persistentlyDismissible()` for that.

```php
AdminNotice::create()
	->text('You can hide this notice by clicking the "(x)" =>')
	->dismissible()
	->show();
```
![Dismissible notice (not persistent)](https://cloud.githubusercontent.com/assets/2527434/24096795/c5d3e0a6-0d6b-11e7-87b3-2ca7b1f143aa.png)

##### `persistentlyDismissible([$scope, $duration])`

Make the notice persistently dismissible. When the user dismisses the notice, the library stores a flag in the database that prevents the notice from showing up again. Persistently dismissible notices must have a unique ID.
 
The `$scope` parameter controls whether clicking "(x)" will hide the notice for everyone or just for the current user. The supported values are:

* `AdminNotice::DISMISS_PER_SITE` - hide the notice site-wide. This is the default.
* `AdminNotice::DISMISS_PER_USER` - hide the notice only for the current user.

Example:
```php
AdminNotice::create('my-notice-id')
	->persistentlyDismissible(AdminNotice::DISMISS_PER_SITE)
	->success('This notice can be permanently dismissed.')
	->show();
```
![Persistently dismissible notice](https://cloud.githubusercontent.com/assets/2527434/24096801/c5f5ccb6-0d6b-11e7-8bd9-42c90e974446.png)

The `$duration` parameter controls how long (in seconds) the notice will be considered dismissed for. By default, notices will be dismissed permanently.

Example:
```php
AdminNotice::create('my-notice-id')
	->persistentlyDismissible(AdminNotice::DISMISS_PER_SITE, WEEK_IN_SECONDS)
	->success('This notice can be dismissed for 1 week.')
	->show();
```

Notes:
* You must load `AdminNotice.php` before the `admin_init` action to make sure that the default AJAX handlers get set up correctly.
* It's safe to call `show()` on a dismissed notice. It won't display the notice, and it won't throw an error either.

##### `dismiss([$duration])`

Persistently dismiss the notice. Only works on notices that have been flagged as `persistentlyDismissible()`.

The `$duration` parameter controls how long (in seconds) the notice will be considered dismissed for. By default, the duration is the same as the duration passed to `persistentlyDismissible()`. You can also pass `AdminNotice::DISMISS_PERMANENTLY` to dismiss the notice permanently.

##### `undismiss()`

Restore a previously dismissed notice.

##### `isDismissed() : boolean`

Check if the notice has been dismissed.

##### `AdminNotice::cleanUpDatabase($prefix)`

Delete all "this notice is dismissed" flags that have the specified ID prefix from the database. If you're using persistently dismissible notices, it's a good idea to call this function when your plugin is uninstalled. 

For example, if your notice IDs start with `myplugin-`, you can remove the database entries like this:
```php
AdminNotice::cleanUpDatabase('myplugin-');
```

### Preconditions

These methods control **where** notices will appear and **who** will be able to see them.
 
##### `onPage($screenId)`

Show the notice only on the specified admin page(s). `$screenId` can be either the screen ID of a page (i.e. a string), or an array of screen IDs.
 
See [Admin Screen Reference](https://codex.wordpress.org/Plugin_API/Admin_Screen_Reference) for a list of screens and their IDs. In the case of plugin and theme admin pages, the screen ID is usually the same as the value returned by the `add_*_page()` function.

Example:
```php
AdminNotice::create()
	->info()
	->text('This message will only appear on the "Plugins -> Installed Plugins" page')
	->onPage('plugins')
	->show();
	
add_action('admin_menu', function() {
	$id = add_options_page(
		'Example',
		'Example',
		'manage_options',
		'example-admin-page',
		'__return_false'
	);

	AdminNotice::create()
		->info()
		->text('This will appear on "Settings -> Example"')
		->onPage($id)
		->show();
});

```

##### `requiredCap($capability)`

Show the notice only to users who have the specified `$capability`.

### Show now or later

After creating a notice, call one of these methods to display it.

##### `show()`

Automagically show the notice on the current page when all preconditions are met.

##### `showOnNextPage()`

Show the notice on the next admin page that's visited by the current user. The notice will be shown only once. 

This method is useful for plugin activation hooks, redirects, and other situations where you can't display a notice immediately for whatever reason. The library will store the notice in the database. It will display the notice the next time that the `admin_notices` action is called in the context of the current user, whether happens during this page load or the next one, or a week later.  

##### `outputNotice()`

Immediately display the notice. This method bypasses any checks and preconditions and just outputs the notice directly. It's mainly intended for debugging.
