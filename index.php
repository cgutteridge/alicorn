<?php

$f3=require('lib/base.php');
require_once('lib/ffrdf/ffrdf.php');

$f3->set('DEBUG',1);
if ((float)PCRE_VERSION<7.9)
	trigger_error('PCRE version is out of date');

$f3->config('config.ini');

$f3->set( "uri_base", "http://dbpedia.org/resource" );

$f3->set( "data_mode", "SPARQL" ); // SPARQL or URI (possibly one big data file too later)

// SPARQL MODE OPTIONS
$f3->set( "sparql_endpoint", "http://dbpedia.org/sparql" );
$f3->set( "sparql_params", array( "format"=>"application/rdf+xml" ) );
// the sparql path to identify if a resource exists. Must include rdf:type
$f3->set( "identity_path", "a|rdfs:label");

// URI MODE OPTIONS
// none yet, just loads the data at the URI


// Handler. When the current URL can be converted to a URI which it's to describe,
// this setting picks what handler to use. The first matching rdf:type from the list
// selects the handler. 
$f3->set( "type_map", array(
        array( "type" => "dbo:City", "handler" => "city" ),     
        array( "type" => "dbo:Place", "handler" => "place" ),     
        array( "type" => "dbo:Genre", "handler" => "genre" ),     
        array( "type" => "dbo:PoliticalParty", "handler" => "party" ),     
        array( "type" => "dbo:Person", "handler" => "person" ),     
));
$f3->set( "default_handler", "default" );

// Templates 

$f3->set( "html", "html.htm" ); // the outer HTML layout 
$f3->set( "template", "template.htm" ); // the template inside <body> 

$f3->set( "ns", new ArrayObject() );
$f3->get("ns")->offsetSet(" geo","http://www.w3.org/2003/01/geo/wgs84_pos#" );
$f3->get("ns")->offsetSet( "sr","http://data.ordnancesurvey.co.uk/ontology/spatialrelations/" );
$f3->get("ns")->offsetSet( "soton","http://id.southampton.ac.uk/ns/" );
$f3->get("ns")->offsetSet( "rooms","http://vocab.deri.ie/rooms#" );
$f3->get("ns")->offsetSet( "dct","http://purl.org/dc/terms/" );
$f3->get("ns")->offsetSet( "event","http://purl.org/NET/c4dm/event.owl#" );
$f3->get("ns")->offsetSet( "gr","http://purl.org/goodrelations/v1#" );
$f3->get("ns")->offsetSet( "dbo","http://dbpedia.org/ontology/" );

// Homepage
$f3->route('GET /',
	function($f3) {
		$f3->set( "page_title", "FFRDF Demo" );
		$f3->set( "content","homepage.htm" );
                print Template::instance()->render( $f3->get( "html" ) );
	}
);

// other non-resource pages go here.


// Resolve any other address and see if it can be mapped to a URI
                               
$f3->route("GET|HEAD *.rdf.html", "debugView" );
$f3->route("GET|HEAD *.@format?@param", "pageView");
$f3->route("GET|HEAD *.@format", "pageView");
$f3->route("GET|HEAD *", "negotiate");

$f3->run();

exit;

