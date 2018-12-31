<?php

const DB_HOSTNAME = '';
const DB_USERNAME = '';
const DB_PASSWORD = '';
const DB_DATABASE = '';
const ADMIN_EMAIL = '';
const ACCESS_TOKEN = '';

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
$resp = json_decode( $latest_str, false );
curl_close( $curl );

// solo continuamos si no hubo un error
if ( $resp->meta->code === 200 ) {
    try {
        // iniciamos la conexion a la base de datos 
        $mysqli = new mysqli( DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE );
        
        // comprobamos que no hayan errores
        if ( $mysqli->connect_errno )
            throw new Exception( $mysqli->connect_error, $mysqli->connect_errno );

        /*
        * Preparamos la sentenia necesaria para ingresar los datos.
        * Como no estamos especificando nombres de columnas, el orden es importante:
        * 1. ig_id
        * 2. fecha
        * 3. urls
        * 4. descripcion
        * 5. pub_url
        */
        $insert_query = "INSERT INTO `acciones_instagram` (`ig_id`, `fecha`, `urls`, `descripcion`, `pub_url`) 
                         VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
                         fecha = VALUES(fecha),
                         urls = VALUES(urls), 
                         descripcion = VALUES(descripcion),
                         pub_url = VALUES(pub_url)";
        $insert_stmt = $mysqli->prepare( $insert_query );

        if ( ! $insert_stmt ) throw new Exception( $mysqli->error, $mysqli->errno );

        // enlazamos los parametros
        $insert_stmt->bind_param( 'sssss', $ig_id, $fecha, $urls, $descripcion, $pub_url );

        
        /* Comenzamos el tratamiento del objeto devuelto por IG */
        $data = $resp->data;
        foreach ( $data as $pub ) {
            $ig_id = $pub->id;
            $fecha = strtotime( fetch_date_str( $pub->tags ) );
            $urls = json_encode( $pub->images );
            $descripcion = $pub->caption->text;
            $pub_url = $pub->link;
            
            // si ocurrio algun error con la fecha, no insertamos
            if ( $fecha === false ) continue;
            $fecha = date( 'Y-m-d', $fecha );
            if ( ! $insert_stmt->execute() ) throw new Exception( $insert_stmt->error, $insert_stmt->errno );
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
    } finally {
        if ( $insert_stmt ) $insert_stmt->close();
        if ( $select_stmt ) $select_stmt->close();
        if ( $mysqli ) $mysqli->close();
    }

}