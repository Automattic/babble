=== Babble ===
Contributors: simonwheatley
Requires at least: 3.4.2
Tested up to: 3.4.2
Stable tag: Alpha 1.1
Tags: translations
 
A plugin to handle translating content into a variety of languages.

== Description ==

This plugin is at a proof of concept stage for translating post type content for the built
in types of `post` and `page`.

The plugin was built with an aversion to both additional database tables, additional columns 
or column changes and a desire to keep additional queries to a minimum.

There are a **lot** of `@FIXME` comments, expressing doubts, fears, uncertainties and 
unknowns; feel free to weigh in on any of them.

Please add bugs to https://github.com/simonwheatley/babble/issues

== Installation ==

Installation is fairly standard:

1. Upload the `babble` directory to the `/wp-content/plugins/` directory
1. Activate pretty permalinks in Admin > , not sure how it will cope without these!
1. Copy the contents of `languages` into `wp-content/languages/` (optional, but makes it easier to see when languages have been switched)
1. Activate the plugin through the 'Plugins' menu in WordPress
1. You'll now be prompted to set the languages you want, you can pick from any of the language packs you've got installed
1. You'll notice the language switcher menu in the admin bar, use this to switch languages and (depending on context) to create new versions of the content you are looking at (from the front end) or editing (from the admin area)

== Screenshots ==

1. Shows the Babble language switch menu in the admin area, showing the site in Hebrew and the options to switch to English or German
2. Shows the Babble language switch menu while editing a post, where a German translation has been created and offering the option to create a Hebrew translation
3. Shows the provisional URL structure on the frontend, and the collapsed Babble language switcher menu in the admin bar

== Changelog ==

= alpha 1.1 =

Taxonomies.

= alpha 1 =

Proof of concept concentrating on the translation of posts. Taxonomies and menus are not handled yet. Widgets are out of scope completely for this phase of work.

