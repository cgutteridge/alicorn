<?php
	
class ItemHandler
{
	var $g;
	var $f3;
	var $uri;
	var $r;
	var $endpoint;
	
	function __construct( $f3, $uri )
	{
		$this->f3 = $f3;
		$this->uri = $uri;
		$this->initGraph();
		$this->r = $this->g->resource( $uri );
		$this->endpoint = $f3->get( "sparql_endpoint" );
	}
	
	function initGraph()
	{
		$this->g = new Graphite();
		$this->g->workAround4StoreBNodeBug = true;
		$this->g->ns( "sr","http://data.ordnancesurvey.co.uk/ontology/spatialrelations/" );
		$this->g->ns( "soton","http://id.southampton.ac.uk/ns/" );
		$this->g->ns( "rooms","http://vocab.deri.ie/rooms#" );
		$this->g->ns( "geo","http://www.w3.org/2003/01/geo/wgs84_pos#" );
		$this->g->ns( "dct","http://purl.org/dc/terms/" );
		$this->g->ns( "event","http://purl.org/NET/c4dm/event.owl#" );
		$this->g->ns( "gr","http://purl.org/goodrelations/v1#" );
	}
	
	var $sparql_path = "./(a|rdfs:label)?";

        function loadData()
        {
                return $this->loadSPARQLPath( $this->sparql_path );
        }
       

	function loadSPARQLPath( $path )
	{
		$n = $this->r->loadSPARQLPath( 
			$this->endpoint, 
			$path, 
			array( "sparql-params"=>$this->f3->get("sparql_params") ) );
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
	
		if( $format == "map" )
		{
			# hmm. Might be better to do this with the already 
			# loaded graph...
			return mapView( $this->uri, $this->f3->get( "sparql_endpoint" ) );
		}
		if( $format == "raw" )  
		{
			$this->f3->set('brand_file', $this->f3->get('raw_template'));
			return $this->serveHTML(); 
		}

		if( $format == "html" )  { return $this->serveHTML(); }
		if( $format == "rdf" )   { return $this->serveRDF(); }
		if( $format == "ttl" )   { return $this->serveTTL(); }
		if( $format == "nt" )    { return $this->serveNT(); }
		if( $format == "kml" )   { return $this->serveKML(); }
		if( $format == "ics" )   { return $this->serveICS(); }
		if( $format == "rdf.html" ) { return $this->serveRDFHTML(); }
	
		$this->f3->error(404);
	}

	function setHeaders()
	{
		$format = $this->f3->get( "format" );

		$map = array(
			"html" => "text/html",
			"rdfhtml" => "text/html",
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
		print "<p>Warning: serveHTML not defined.</p>";
		return $this->serveRDFHTML();
	}
	
	function serveRDFHTML()
	{
		print "<p>This is a dump of the data used to generate this page. It's intended for programmers and data experts only!</p>";
		print $this->g->dump();
	}
	
	function serveTTL() { print $this->g->serialize( "Turtle" ); }

	function serveNT() { print $this->g->serialize( "NTriples" ); }

	function serveRDF() { print $this->g->serialize( "RDFXML" ); }

	function serveKML() { print $this->g->toKml(); }

	function serveICS() { print $this->g->toIcs(); }

}
