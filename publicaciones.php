<?php
    
include 'credenciales.php';

if ( ! empty( $_GET ) ) { 
    if ( isset($_GET['fecha'] ) && $fecha = $_GET['fecha'] ) {
        $fecha = date_format( date_create( $fecha ), 'Y-m-d' );
        if ( $fecha !== FALSE ) {
            $db = new InstariseDB(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
            $result = $db->select($fecha);
            if ( $result && is_array( $result ) ) echo json_encode( $result );
            else echo "Ocurrio un error procesando la solicitud";

            exit();
        }
    } 
}
http_response_code(403);