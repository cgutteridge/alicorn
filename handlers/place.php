<?php

#################

class PlaceHandler extends ItemHandler {

	var $sparql_path = ".|^dbo:birthPlace/(dbo:abstract|foaf:name|(dbo:genre|dbo:party)/rdfs:label)";

        function serveHTML()
        {      
                $this->f3->set( "content", "place.htm" );
                print Template::instance()->render( $this->f3->get( "html" ) );
        }      
}      

