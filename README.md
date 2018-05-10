# Candela (DEPRECATED)

Candela is a book authoring platform built on top of [Wordpress Multisite](https://codex.wordpress.org/Create_A_Network) and
[Pressbooks](https://github.com/pressbooks/pressbooks).  This project is no longer maintained and may not function as
expected; however, the plugins this project uses are still used and maintained, and can be found below:

[Candela Utility](https://github.com/lumenlearning/candela-utility): Candela helper plugin to manage additional config and bootstrapping
[Candela Citation](https://github.com/lumenlearning/candela-citation): A plugin that extends creative commons attribution functionality to Candela/Pressbooks
[Candela Thin Exports](https://github.com/lumenlearning/candela-thin-exports): A simple plugin to export Pressbooks books as thin cartridges with deep links to each page
[Candela LTI](https://github.com/lumenlearning/candela-lti): A plugin that implements the LTI/Wordpress integration
[Candela Outcomes](https://github.com/lumenlearning/candela-outcomes): A plugin that adds an Outcomes meta field for coursework
[Bombadil Theme](https://github.com/lumenlearning/bombadil): A Wordpress Theme based on the Pressbooks Luther Theme

## Install Wordpress

The following instructions assume basic familiarity with installing Wordpress, and that this codebase is located in a web accessible location.

1. Navigate to your installed base.
1. Click "Create a Configuration File"
1. Click "Let's go!"
1. DB Connection details;
  * Database Name: `******`
  * User Name: `******`
  * Password: `******`
  * Database Host: `localhost`
  * Table Prefix: `wp_`
1. Click "Run the install"
1. Welcome
  * Site Title: `Candela`
  * Username: `******`
  * Password: `******`
  * Email: `******`
1. Edit wp-config.php and add the following just above the line `/* That's all, stop editing! Happy blogging. */
`;
````
    /* Multisite */
    define( 'WP_ALLOW_MULTISITE', true );

    /* Pressbooks configuration */
    define( 'PB_PRINCE_COMMAND', '/usr/bin/prince' );
    define( 'PB_KINDLEGEN_COMMAND', '/home/vagrant/kindlegen/kindlegen' );
    define( 'PB_EPUBCHECK_COMMAND', '/usr/bin/java -jar /home/vagrant/epubcheck-3.0.1/epubcheck-3.0.1.jar' );
    define( 'PB_XMLLINT_COMMAND', '/usr/bin/xmllint' );
````
1. Click "Log in"
1. Login with details just created.
1. Tools -> Network Setup
1. Choose 'sub-directories'
1. Click "Install"
1. Follow on screen instructions making appropriate edits to `wp-config.php` and `.htaccess`

## Enable and Configure Pressbooks and Pressbooks Textbooks

1. Once above edits are made relogin
1. Navigate to "My Sites" -> "Network Admin" -> "Plugins"
1. "Network Activate" Pressbooks
1. "Network Activate" Disable Comments
1. "Network Activate" Candela Analytics
1. "Network Activate" Candela Citations
1. "Network Activate" Candela Links
1. "Network Activate" Candela Utility
1. Use the "Settings" link for Disable Comments to access the settings page. Select "Everywhere: Disable all comment-related controls and settings in WordPress." Click the "Save Changes" button.
1. Navigate to "My Catalog" -> "Network Admin" -> "Themes"
1. Select "Installed Themes"
1. "Network Enable" the "Candela" theme
1. Navigate to "My Catalog" -> "Network Admin" -> "Dashboard"
1. Select "Settings" -> "Network Settings"
1. In the "Allow new registrations" section, select: "Logged in users may register new sites." This allows members with adequate privileges to create their own books
  * the other options on this page should be set/adjusted to suit administrative needs/preference
1. Navigate to "My Catalog" -> "Add a New Book"
1. Get writing!

## Hypothes.is

The hypothes.is functionality is included as part of Pressbooks Textbooks and needs to be enabled on a book by book basis. To enable annotation in your books:

1. Navigate to "PB Textbook" admin section in the book where you want to enable Hypothes.is;
1. Click the "Other" tab;
1. Select "Yes. I would like to add annotation functionality to my book pages."
1. Click the "Save Changes" button.


## Enable and configure LTI and candela LTI

1. Navigate to "My Catalog" -> "PRIMARY SITE NAME" -> "Dashboard"
1. Click "Plugins"
1. "Activate" LTI
1. Navigate to "My Sites" -> "Network Admin" -> "Plugins"
1. "Network Activate" Candela LTI
1. In the left admin menu (primary site only), navigate to "LTI Consumers" -> "Add New."
