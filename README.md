## Pre-requisites
1. Latest version of virtualbox (4.3.10+), latest version of vagrant (1.5.2)
  Ubuntu's apt is too out of date. Please use the latest versions from Oracle and Vagrant;
    * https://www.virtualbox.org/wiki/Linux_Downloads
    * http://www.vagrantup.com/downloads.html
1. vagrant-vbguest plugin `vagrant plugin install vagrant-vbguest`
1. Working NFS server
    `apt-get install nfs-kernel-server`

See also See also https://github.com/FunnyMonkey/fm-vagrant/blob/streamline/README.md
if you run into any issues with `vagrant up` or setting up virtualbox or vagrant.

## Setup
1. Checkout repository
    git clone https://github.com/FunnyMonkey/candela.git
1. `cd candela`
1. `vagrant up`
1. Navigate to http://192.168.33.10
1. Click "Create a Configuration File"
1. Click "Let's go!"
1. DB Connection details;
  * Database Name: `wordpress`
  * User Name: `wordpress`
  * Password: `wordpress`
  * Database Host: `localhost`
  * Table Prefix: `wp_`
1. Click "Run the install"
1. Welcome
  * Site Title: `Candela`
  * Username: `******`
  * Password: `******`
  * Email: `******`
1. Click "Log in"
1. Login with details just created.
1. Tools -> Network Setup
1. Choose 'sub-directories'
1. Click "Install"
1. Follow on screen instructions making appropriate edits to `www/192.168.33.10/wp-config.php` and `www/192.168.33.10/.htaccess`
1. Add the following lines to wp-config.php just after the lines you added in the previous step.
    define( 'PB_PRINCE_COMMAND', '/usr/bin/prince' );
    define( 'PB_KINDLEGEN_COMMAND', '/home/vagrant/kindlegen/kindlegen' );
    define( 'PB_EPUBCHECK_COMMAND', '/usr/bin/java -jar /home/vagrant/epubcheck-3.0.1/epubcheck-3.0.1.jar' );
    define( 'PB_XMLLINT_COMMAND', '/usr/bin/xmllint' );
1. Once above edits are made relogin: http://192.168.33.10/wp-login.php
1. "My Sites" -> "Network Admin" -> "Plugins"
1. "Network Activate" Pressbooks
1. "Network Activate" PressBooks Textbook
1. "Network Activate" Hypothesis
