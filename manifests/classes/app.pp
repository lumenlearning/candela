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
}
