# Edit this file to suit your application needs.
class app {
  include fm_apache_php
  include php_devel

  include fm_mysql
  mysql_user {'wordpress@localhost':
    ensure                    => 'present',
    max_connections_per_hour  => '0',
    max_queries_per_hour      => '0',
    max_updates_per_hour      => '0',
    max_user_connections      => '0',
    password_hash             => '*C260A4F79FA905AF65142FFE0B9A14FE0E1519CC',
  }
  mysql_database {'wordpress':
    ensure  => 'present',
    charset => 'utf8',
  }
  mysql_grant { 'wordpress@localhost/vagrant.*':
    ensure      => 'present',
    options     => ['GRANT'],
    privileges  => ['ALL'],
    table       => 'wordpress.*',
    user        => 'wordpress@localhost'
  }


  # Setup Pressbooks dependencies
  # xmllint
  package { libxml2-utils:
    ensure => installed,
  }

  # install epubcheck
  exec { 'install epubcheck':
    command => '/usr/bin/wget -O /home/vagrant/epubcheck.zip https://github.com/IDPF/epubcheck/releases/download/v3.0.1/epubcheck-3.0.1.zip && unzip /home/vagrant/epubcheck.zip -d /home/vagrant',
    require => Package['unzip'],
    creates => '/home/vagrant/epubcheck-3.0.1/epubcheck-3.0.1.jar',
  }

  # install kindlegen
  exec { 'install kindlegen':
  command => 'mkdir /home/vagrant/kindlegen && /usr/bin/wget -O /home/vagrant/kindlegen/kindlegen.tar.gz http://kindlegen.s3.amazonaws.com/kindlegen_linux_2.6_i386_v2_9.tar.gz && tar --directory=/home/vagrant/kindlegen -xvzf /home/vagrant/kindlegen/kindlegen.tar.gz',
  creates => '/home/vagrant/kindlegen/kindlegen',
  }

  # install prince
  package { libtiff4:
    ensure => installed
  }
  package { libgif4:
    ensure => installed
  }
  exec { 'install prince':
    command => '/usr/bin/wget -O /home/vagrant/prince.deb http://www.princexml.com/download/prince_9.0-4_ubuntu12.04_amd64.deb && sudo dpkg -i /home/vagrant/prince.deb',
    require => Package['libgif4', 'libtiff4'],
    creates => '/usr/bin/prince',
  }

}
