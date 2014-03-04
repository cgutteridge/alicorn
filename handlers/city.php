<?php

#################

class CityHandler extends ItemHandler {

	var $sparql_path = "./(a|rdfs:label)|^<http://dbpedia.org/ontology/birthPlace>/.";

        function serveHTML()
        {      
                $this->f3->set( "content", "city.htm" );
       
                print Template::instance()->render( $this->f3->get( "html" ) );
        }      
}      

