<?php

$f3=require('lib/base.php');
require('lib/arc2/ARC2.php');
require('lib/Graphite/Graphite.php');
require('lib/ffrdf/ItemHandler.php');

$f3->set('DEBUG',1);
if ((float)PCRE_VERSION<7.9)
	trigger_error('PCRE version is out of date');

$f3->config('config.ini');

$f3->set( "uri_base", "http://dbpedia.org/resource" );
$f3->set( "sparql_endpoint", "http://dbpedia.org/sparql" );
$f3->set( "sparql_params", array( "format"=>"application/rdf+xml" ) );
$f3->set( "identity_path", "a"); // the sparql path to identify if a resource exists. Must include rdf:type
$f3->set( "identity_path", "a|rdfs:label");
$f3->set( "type_map", array(
        array( "type" => "http://dbpedia.org/ontology/City", "handler" => "city" ),     
));
$f3->set( "default_handler", "default" );
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
		print "Hello, world";
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

/// Userspace

function getRelatedURI( $f3, $params )
{
	@list( $path, $querystring ) = explode("?", $f3->get("URI"), 2);
	$uri = preg_replace("/\\." . $params['format'] . "$/", "", $f3->get( "uri_base" ) . $path );
	return $uri;
}

function localPathToURI( $path )
{
	$f3 = Base::instance();

	# ignore everything after a "?"
	@list( $path, $querystring ) = explode("?", $path, 2);
	
	# remove any format suffix
	$path_sans_suffix = preg_replace( '/\.[^\/]*/', '', $path );
	
	$uri = $f3->get( "uri_base" ).$path_sans_suffix;
	return $uri;	
}

function URIToLocalURL( $uri )
{
	$f3 = Base::instance();

	if( strpos( $uri, $f3->get( "uri_base" ) ) !== 0 )
	{
		# URI does not start with uri_base so return as-is
		return $uri;
	}

	$path = substr( $uri, strlen($f3->get( "uri_base" )) );

	return "$path.html";	
}


/// Library

function ffrdf_prettyLink( $resource )
{
	$label = $resource->uri;
	if( $resource->hasLabel() ) { $label = $resource->label(); }
	return "<a title='".$resource->uri."' href='".URIToLocalURL($resource->uri)."'>$label</a>";
}

function debugView( $f3, $params )
{
	$params["format"]="rdf.html"; 
	pageView( $f3, $params );
}

function negotiate($f3)
{
	$uri = $f3->get('URI');
	$ext = resolver( $f3 );
	header("Location: " . $uri . ".".$ext, true, 302);
}


function pageView($f3, $params)
{	      
	$uri = localPathToURI($f3->get("URI"));
	$graph = new Graphite();
	$resource = $graph->resource( $uri );
	$n = $resource->loadSPARQLPath( 
		$f3->get( "sparql_endpoint" ), 
		$f3->get( "identity_path" ), 
		array( "sparql-params"=>$f3->get("sparql_params") ) );

	if( $n == 0 )
	{
		$f3->error(404);
		exit();
	}      
       
	$type_config = array( "handler" => $f3->get('default_handler') );

	# try types in order
	foreach( $f3->get( "type_map" ) as $type_i )
	{
		foreach($resource->types() as $type)
		{
			if( (string)$type == $type_i["type"] )
			{
				$type_config = $type_i;
				break;
			}      
		}      
	}      

	# new-style handler
	require_once( "handlers/".$type_config["handler"].".php" );
	$handlerClass = "{$type_config['handler']}Handler";
	$f3->set('format', $params["format"] );
	$handler = new $handlerClass( $f3, $uri );
	try {
		$handler->loadData();
	} catch (Exception $e ) {
		echo "Bugger: ".$e->message();
		return;
	}
	$handler->serveDocument();
}
 
function resolver($f3) 
{                      
        $req = $f3->get('SERVER');
       
        $views = array(
                array(
                        "mimetypes"=>array( "text/html" ),
                        "ext"=>"html" ),
                array( 
                        "mimetypes"=>array( "text/turtle", "application/x-turtle" ),
                        "ext"=>"ttl" ),
                array( 
                        "mimetypes"=>array( "application/rdf+xml" ),
                        "ext"=>"rdf" ),
                array( 
                        "mimetypes"=>array( "text/plain", "text/csv" ),
                        "ext"=>"csv" ),
        );             
       
        $ext = "html";
        if( isset( $req["HTTP_ACCEPT"] ) )
        {
                $opts = preg_split( "/,/", $req["HTTP_ACCEPT"] );
                $o = array( "text/html"=>0.1 , "application/rdf+xml"=>0 );
                foreach( $opts as $opt)
                {
                        $opt = trim( $opt );
                        $optparts = preg_split( "/;/", $opt );
                        $mime = array_shift( $optparts );
                        $o[$mime] = 1;
                        foreach( $optparts as $optpart )
                        {
                                $optpart = trim( $optpart );
                                list( $k,$v ) = preg_split( "/=/", $optpart );
                                $k = trim( $k );
                                $v = trim( $v );
                                if( $k == "q" ) { $o[$mime] = $v; }
                        }      
                }      

                $score = 0.1;
                foreach( $views as $view )
                {
                        foreach( $view['mimetypes'] as $mimetype )
                        {
                                if( @$o[$mimetype] > $score )
                                {
                                        $score=$o[$mimetype];
                                        $ext = $view["ext"];
                                }      
                        }      
                }      
        }      

        return $ext;
}

