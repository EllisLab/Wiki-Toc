# Wiki-Toc

NOTE: This plugin requires ExpressionEngine 1.5 or greater and PHP 4.3.2 or greater.
There are four user settings in the pi.wiki_toc.php file that you use to control the
plugin settings.

## Settings

### $formatting

This setting controls which formatting plugin will be used to control the text formatting for
your articles. You must use the class name of the plugin, which will always have the first
letter capitalized, and it will match the plugin filename, without the pi. and .php portions.
e.g.: the Textile plugin file is named pi.textile.php and the class name is Textile
The default setting is 'default', and uses ExpressionEngine's XHTML Typography class.

### $toc_tag

This setting controls the tag that you will use in your articles to place the Table of Contents.
If you change this, use a unique combination of characters that won't be encountered in the
body of any of your articles. The default is '[TOC]'

### $heading

This setting is to allow for easy localization of the plugin. This will be used as a
label for your table of contents. The default is 'Table of Contents'.

### $separator

This setting contains the XHTML markup that will be placed as a separator between the table of
contents and the article. The default markup is a horizontal rule: <hr />.

## USAGE:

Select "Wiki TOC" as your formatting option in your Wiki Preferences.

Place your table of contents tag at the top of an article that you wish to have a table of contents: `[TOC]`.

Create headings with [h#] or <h#> tags that will generate the jump points:
- `[h4]Your Heading[/h4]`
- `[h5]Your Subheading[/h5]`

The plugin will format your article with your preferred formatting plugin, and create a table of contents
in place of your `[TOC]` tag, in the form of an HTML unordered list. The list is given an id="toc" to
facilitate styling via CSS.

## CHANGELOG:

- Version 1.1 - Added support for UTF-8 headings. Requires PHP 4.3.2 (or 4.2 with the PCRE library
compiled correctly).
- Version 1.2 - Modified for compatibility with the current typography class (EE 1.6.5+).
- Version 1.3 - Updated plugin to be 2.0 compatible
