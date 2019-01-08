<?php

include 'credenciales.php';

function fetch_date_str( array $tags ) {
    $m = array( 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic' );
    $e = array( 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec' );
    foreach( $tags as $i => $tag ) 
        if ( is_numeric( substr( $tag, 0, 2 ) ) && ( $c = array_search( substr( $tag, 2 ), $m ) ) !== false )
            return str_replace( substr( $tag, 2 ), $e[$c], $tag );
    return '';
}



$token = ACCESS_TOKEN;

$ig_recent_media_url = "https://api.instagram.com/v1/users/self/media/recent/?access_token=". ACCESS_TOKEN;
$curl = curl_init( $ig_recent_media_url );
curl_setopt( $curl, CURLOPT_HTTPGET, true );
curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );

$latest_str = curl_exec( $curl );
$resp = json_decode( $latest_str, true );
curl_close( $curl );

// solo continuamos si no hubo un error
if ( $resp['meta']['code'] === 200 ) {
    try {
        // iniciamos la conexion a la base de datos 
        $db = new InstariseDB( DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE );
        
        // comprobamos que no hayan errores
        if ( $error = $db->last_error_as_array() )
            throw new Exception( $error['error'], $error['errno'] );

        $data = $resp['data'];
        foreach ( $data as $pub ) {
            $ig_id = $pub['id'];
            $fecha = strtotime( fetch_date_str( $pub['tags'] ) );
            $urls = json_encode( $pub['images'] );
            $descripcion = $pub['caption']['text'];
            $pub_url = $pub['link'];
            
            // si ocurrio algun error con la fecha, no insertamos
            if ( $fecha === false ) continue;
            $fecha = date( 'Y-m-d', $fecha );
            if ( ! $db->insert( compact( 'ig_id', 'fecha', 'urls', 'descripcion', 'pub_url' ) )  ) throw new Exception( $db->get_last_error_as_array()['error'], $db->get_last_error_as_array()['errno'] );
        }
        
    } catch (Exception $e) {
        // componemos un mensaje de error 
        $error_message = '<p>Hubo un error con la base de datos: <br><code>%s</code><br><strong>C&oacute;digo de error: %d</strong>'
            . "<br><pre>&#9;</pre>Por favor ponerse en contacto con los desarrolladores:<br>"
            . '<ul><li><a href="mailto:migueldeolim1@gmail.com">Miguel De Olim</a></li>'
            . '<li><a href="mailto:tomaselfakih@gmail.com">Tom&aacute;s El Fakih</a></li></ul></p>';
    
        // cabeceras de correo
        $email_headers = array(
            'From: no-reply@venactivate.org',
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/html'
        );
    
        // mandamos el mensaje de error
        mail( ADMIN_EMAIL, '[venactivate.org] - Error', sprintf( $error_message, $e->getMessage(), $e->getCode() ), implode( "\r\n", $email_headers ) );
    }

}