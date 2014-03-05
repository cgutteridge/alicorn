<?php

#################

class PersonHandler extends ItemHandler {

	var $sparql_path = ".|(dbo:genre|dbo:party|dbo:birthPlace)/rdfs:label";

        function serveHTML()
        {      
                $this->f3->set( "content", "person.htm" );
                print Template::instance()->render( $this->f3->get( "html" ) );
        }      
}      

