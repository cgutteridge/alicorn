<?php

$f3=require('lib/ffrdf/FFRDF.php');

$f3->set('DEBUG',1);
if ((float)PCRE_VERSION<7.9)
	trigger_error('PCRE version is out of date');

$f3->config('ffrdf.ini'); // ffrdf settings

// Homepage

$f3->route('GET /',
	function($f3) {
		$f3->set( "page_title", "FFRDF Demo" );
		$f3->set( "content","homepage.htm" );
                print Template::instance()->render( $f3->get( "html_template" ) );
	}
);

// other non-resource pages go here.


// Add the FFRDF routes to catch all other URLs
FFRDF::instance()->addRoutes($f3);

$f3->run();

exit;

