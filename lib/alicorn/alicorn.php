<?php

require('lib/arc2/ARC2.php');
require('lib/Graphite/Graphite.php');
require('lib/alicorn/itemhandler.php');
$f3 = require_once( "lib/base.php" );

class Alicorn extends Prefab {

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

static function initGraph( $f3 )
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
	

function prettyLink( $resource )
{
	$label = $resource->uri;
	if( $resource->hasLabel() ) { $label = $resource->label(); }
	return "<a title='".$resource->uri."' href='".$this->URIToLocalURL($resource->uri)."'>$label</a>";
}

function debugView( $f3, $params )
{
	$params["format"]="debug"; 
	$this->pageView( $f3, $params );
}

function negotiate($f3)
{
	$uri = $f3->get('URI');
	$ext = $this->resolver( $f3 );
	header("Location: " . $uri . ".".$ext, true, 302);
}


function pageView($f3, $params)
{	      
	$uri = $this->localPathToURI($f3->get("URI"));
	$graph = Alicorn::initGraph( $f3 ); 
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

	$type_config = array( "handler" => $f3->get('default_handler'), "id"=>"default" );

	# try types in order
	$order = @$f3->get( "class_order" );
	if( isset( $order ) )
	{
		if( !is_array( $order ) ) { $order = array( $order ); }
	}
	else
	{
		# if no order given, just use any old order
		$order = array_keys( $f3->get( "class" ) );
	}

	foreach( $order as $class_id )
	{
		$class = $f3->get( "class.$class_id" );
		$type_i_uri = $graph->expandURI( $class["rdf_type"] );
		foreach($resource->types() as $type)
		{
			if( (string)$type == $type_i_uri )
			{
				$type_config = $class;
				$type_config["id"] = $class_id;
				break 2;
			}      
		}      
	}      

	# the bit of the path minus the format
	$document_uri = $f3->get( "URI" );
	$document_uri = preg_replace( "/\?.*$/", "", $document_uri );
	$document_uri = preg_replace( "/\.[^\.]*$/","", $document_uri );
	$f3->set('document_uri', $document_uri );

	$f3->set('format', $params["format"] );

	if( isset( $type_config["map_zoom"] ) )
	{
		$f3->set('map_zoom',  $type_config["map_zoom"] );
	}

	$handler_id = $f3->get('default_handler');
	if( isset( $type_config["handler"] ) ) { $handler_id = $type_config["handler"]; }

	if( !isset( $type_config["content_template"] ) )
	{
		$type_config["content_template"] = $type_config["id"].".htm";
	}

	try {
		include_once( "handlers/$handler_id.php" );
	}
	catch( Exception $e ) {
		trigger_error( "Error during loading $handler_id: ".$e->getMessage() );
		return;
	}
	$handlerClass = "{$handler_id}Handler";
	try {
		$handler = new $handlerClass( $f3, $uri, $graph, $type_config );
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

function addRoutes($f3)
{                               
	$f3->route("GET|HEAD *.@format?@param", "Alicorn->pageView");
	$f3->route("GET|HEAD *.@format", "Alicorn->pageView");
	$f3->route("GET|HEAD *", "Alicorn->negotiate");
}

}

$alicorn = Alicorn::Instance();

# set some sensible defaults
$f3->set("data_mode","SPARQL" );
$f3->set("identity_path","a|rdfs:label" );
$f3->set("default_handler","default" );
$f3->set("html_template","html.htm" );
$f3->set("page_template","page.htm" );
$f3->set("content_template","default.htm" );
$f3->set("raw_template","raw.htm" );
$f3->set("debug_template","debug.htm" );
$f3->set("map_template","embed_map.htm" );
$f3->set("map_zoom","9" );
$f3->set("format","static" );


$f3->rdf = $alicorn;
return $f3;
