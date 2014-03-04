<?php

#################

class DefaultHandler extends ItemHandler {

        function serveHTML()
        {      
                $this->f3->set( "content", "default.htm" );
       
                print Template::instance()->render( $this->f3->get( "html" ) );
        }      
}      

