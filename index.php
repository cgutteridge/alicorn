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
$f3->set( "type_map", array(
        array( "type" => "http://dbpedia.org/ontology/City", "handler" => "city" ),     
));
$f3->set( "default_handler", "default" );
$f3->set( "html", "html.htm" ); // the outer HTML layout 
$f3->set( "template", "template.htm" ); // the template inside <body> 

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

/// Library

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
	$uri = getRelatedURI($f3,$params);

	$graph = new Graphite();
	$resource = $graph->resource( $uri );
	$n = $resource->loadSPARQLPath( 
		$f3->get( "sparql_endpoint" ), 
		"a", array( "sparql-params"=>$f3->get("sparql_params") ) );

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

