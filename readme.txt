=== Babble ===
Contributors: simonwheatley
Requires at least: 3.3-aortic-dissection
Tested up to: 3.3-aortic-dissection
Stable tag: Alpha 1
Tags: translations
 
A plugin to handle translating content into a variety of languages.

== Description ==

This plugin is at a proof of concept stage for translating post type content for the built
in types of `post` and `page`.

The plugin is built with the Automattic VIP Hosting Environment in mind, and, hopefully, to
WordPress development best practices.

There are a **lot** of `@FIXME` comments, expressing doubts, fears, uncertainties and 
unknowns; feel free to weigh in on any of them.

Please send bugs to simon@sweetinteraction.com, or enter them on https://github.com/simonwheatley/babble/issues

== Installation ==

Installation is a little more involved than usual.

1. Upload the `babble` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Copy the contents of `languages` into `wp-content/languages/` (optional, but makes it easier to see when languages have been switched)
1. Activate pretty permalinks, not sure how it will cope without these!

If you're not running an up to date copy of WordPress Trunk, then:

1. Apply the `new_hook.diff` to add the `registered_post_type` hook to before `return $args;` in the `register_post_type` function in the `wp-includes/post.php` file.
1. Apply the `new_tax_hook.diff` to add the `registered_taxonomy` hook to the end of the `register_taxonomy` function in the `wp-includes/taxonomy.php` file.

== Screenshots ==

1. Shows the Babble language switch menu in the admin area, showing the site in Hebrew and the options to switch to English or German
2. Shows the Babble language switch menu while editing a post, where a German translation has been created and offering the option to create a Hebrew translation
3. Shows the provisional URL structure on the frontend, and the collapsed Babble language switcher menu in the admin bar

== Changelog ==

= alpha 1 =

Proof of concept concentrating on the translation of posts. Taxonomies and menus are not handled yet. Widgets are out of scope completely for this phase of work.

