<?php

#################

class PartyHandler extends ItemHandler {

	var $sparql_path = ".|^dbo:party/(rdfs:label|dbo:abstract)";

        function serveHTML()
        {      
                $this->f3->set( "content", "party.htm" );
                print Template::instance()->render( $this->f3->get( "html" ) );
        }      
}      

