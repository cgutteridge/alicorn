<?php

require('lib/arc2/ARC2.php');
require('lib/Graphite/Graphite.php');
require('lib/ffrdf/ItemHandler.php');

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

function ffrdf_initGraph( $f3 )
{
	$g = new Graphite();
	$g->workAround4StoreBNodeBug = true;

	# add the site namespaces to this graph
	foreach( $f3->get("ns") as $k=>$v ) 
	{ 
		$g->ns( $k, $v );
	}

	return $g;
}
	

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
	$graph = ffrdf_initGraph( $f3 ); 
	$resource = $graph->resource( $uri );

	if( $f3->get( "data_mode" ) == "SPARQL" )
	{
		$n = $resource->loadSPARQLPath( 
			$f3->get( "sparql_endpoint" ), 
			$f3->get( "identity_path" ), 
			array( "sparql-params"=>$f3->get("sparql_params") ) );
	}
	elseif( $f3->get( "data_mode" ) == "URI" )
	{
		$n = $resource->load();
	}
	else
	{
		trigger_error( "Unknown data_mode: ".$f3->get( "data_mode" ) );
	}
	if( $n == 0 )
	{
		$f3->error(404);
		exit();
	}      

	$type_config = array( "handler" => $f3->get('default_handler') );

	# try types in order
	foreach( $f3->get( "type_map" ) as $type_i )
	{
		$type_i_uri = $graph->expandURI( $type_i["type"] );
		foreach($resource->types() as $type)
		{
			if( (string)$type == $type_i_uri )
			{
				$type_config = $type_i;
				break;
			}      
		}      
	}      

	$f3->set('format', $params["format"] );

	try {
		include_once( "handlers/".$type_config["handler"].".php" );
	}
	catch( Exception $e ) {
		trigger_error( "Error during loading ".$type_config["handler"].": ".$e->getMessage() );
		return;
	}
	$handlerClass = "{$type_config['handler']}Handler";
	try {
		$handler = new $handlerClass( $f3, $uri, $graph );
	}
	catch( Exception $e ) {
		trigger_error( "Error during ".$handlerClass."->new( '$uri' ): ".$e->getMessage() );
		return;
	}
	try {
		$handler->loadData();
	} 
	catch( Exception $e ) {
		trigger_error( "Error during ".$handlerClass."->loadData( '$uri' ): ".$e->getMessage() );
		return;
	}
	try {
		$handler->serveDocument();
	} 
	catch( Exception $e ) {
		trigger_error( "Error during ".$handlerClass."->serveDocument(): ".$e->getMessage() );
		return;
	}
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

