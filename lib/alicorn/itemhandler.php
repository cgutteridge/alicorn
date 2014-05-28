<?php

	
class ItemHandler
{
	var $g;
	var $f3;
	var $uri;
	var $r;
	var $endpoint;

	var $html;
	var $content;
	var $template;
	
	var $sparql_path = "./(a|rdfs:label)?";

	function __construct( $f3, $uri, $g=null, $config=array() )
	{
		$this->f3 = $f3;
		$this->uri = $uri;
		if( !isset( $g ) ) { $g = Alicorn::initGraph( $f3 ); }
		$this->g = $g;
		$this->r = $this->g->resource( $uri );
		$this->endpoint = $f3->get( "sparql_endpoint" );

		$this->html_template = $this->f3->get( "html_template" );
		if( isset( $config["html_template"] ) ) 
		{ 
			$this->html_template = $config["html_template"]; 
		}

		$this->content_template = $this->f3->get( "content_template" );
		if( isset( $config["content_template"] ) ) 
		{ 
			$this->content_template = $config["content_template"]; 
		}

		if( isset( $config["sparql_path"] ) ) { $this->sparql_path = $config["sparql_path"]; }
	}
	

        function loadData()
        {
                return $this->loadSPARQLPath( $this->sparql_path );
        }
       

	function loadSPARQLPath( $path )
	{
		$opts =	array();
		$opts["sparql-params"] = $this->f3->get("sparql_params");
		$opts["union-then-sequence"] = true;
		$n = 0;
		try {
			$n = $this->r->loadSPARQLPath( $this->endpoint, $path, $opts );
		}
		catch( Exception $e )
		{
    			print 'Caught exception: '.  $e->getMessage(). " (line ".$e->getLine()." of ".$e->getFile().")\n";
		}
		return $n;
	}

	function title()
	{
		return $this->r->label();
	}
	
	# subclass to add (or remove) formats
	function serveDocument()
	{
		$this->setHeaders();

		# No need to do the content on a HEAD request
		if( $this->f3->get("VERB") == "HEAD" ) { return; }

		$this->f3->set( "uri", $this->uri );
		$this->f3->set( "graph", $this->g );
		$this->f3->set( "resource", $this->r );
		$this->f3->set( "page_title", $this->title() );

		$format = $this->f3->get( "format" );
	
		if( $format == "raw" ) 
		{
			$this->f3->set('html_template', $this->f3->get('raw_template'));
			return $this->serveHTML(); 
		}

		if( $format == "html" )  { return $this->serveHTML(); }
		if( $format == "rdf" )   { return $this->serveRDF(); }
		if( $format == "ttl" )   { return $this->serveTTL(); }
		if( $format == "nt" )    { return $this->serveNT(); }
		if( $format == "kml" )   { return $this->serveKML(); }
		if( $format == "map" )   { return $this->serveMap(); }
		if( $format == "ics" )   { return $this->serveICS(); }
		if( $format == "debug" ) { return $this->serveDebug(); }
	
		$this->f3->error(404);
	}

	function setHeaders()
	{
		$format = $this->f3->get( "format" );

		$map = array(
			"html" => "text/html",
			"debug" => "text/html",
			"map" => "text/html",
			"kml" => "Content-type: application/vnd.google-earth.kml+xml",
			"ttl" => "text/turtle",
			"nt" => "text/plain",
			"rdf" => "application/rdf+xml", 
			"ics" => "text/calendar" );

		if( isset( $map[$format] ) )
		{
			header( "Content-type: ".$map[$format] ); 
		}
	}

	function serveHTML()
	{
		$format = $this->f3->set( "content", $this->content_template );
                print Template::instance()->render( $this->html_template );
	}
	
	function serveDebug()
	{
		$format = $this->f3->set( "content", $this->f3->get( "debug_template" ) );
                print Template::instance()->render( $this->html_template );
	}

	function serveMap() 
	{ 
                print Template::instance()->render( $this->f3->get( "map_template" ) );
	}

	
	function serveTTL() { print $this->g->serialize( "Turtle" ); }

	function serveNT() { print $this->g->serialize( "NTriples" ); }

	function serveRDF() { print $this->g->serialize( "RDFXML" ); }

	function serveKML() { print $this->g->toKml(); }

	function serveICS() { print $this->g->toIcs(); }

}
