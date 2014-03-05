<?php

#################

class GenreHandler extends ItemHandler {

	var $sparql_path = ".|(dbo:derivative|dbo:musicSubgenre|dbo:stylisticOrigin)/(rdfs:label|dbo:abstract?)";

        function serveHTML()
        {      
                $this->f3->set( "content", "genre.htm" );
                print Template::instance()->render( $this->f3->get( "html" ) );
        }      
}      

