======
README
======

openerplib es una librería para PHP que permite realizar operaciones xml-rpc con OpenERP 
cómodamente.

Está inspirada en ORM de Django https://www.djangoproject.com/

Dependencies
============

openerlib depende de:

* xmlrpc.inc >= 1.174 http://phpxmlrpc.sourceforge.net/

Installation
============

No requiere ningún tipo de instalación especial. Copiar directorio /openerplib 
donde ser quiera utilizar e importar a tu script php.

Configuration
=============

Dos forma de uso.

1. Configurar fichero /openerplib/openerplib.inc.php

::

define('_OPENERPLIB_BD_', '');
define('_OPENERPLIB_UID_', 0);
define('_OPENERPLIB_PASSWD_', '');
define('_OPENERPLIB_URL_', 'http://<URL>/xmlrpc');

2. Configuración on-live.

::

<?php
	$config = array(
	    'bd'        => 'mybdname',
	    'uid'       => 1212,
	    'passwd'    => 'foo',
	    'url'       => 'http://openerp/xmlrpc',
    );
	
	$open = new OpenERP($config);
?>

Usage
=====

Creando la factoria de objetos OpenERP

::
$open = new OpenERP();	// read config => openerlib.inc.php



Contacts
========

openerplib is written by:

* Benito Rodriguez brarcos@gmail.com