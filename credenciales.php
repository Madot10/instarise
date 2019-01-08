<?php
const DB_HOSTNAME = '';
const DB_USERNAME = '';
const DB_PASSWORD = '';
const DB_DATABASE = '';
const ADMIN_EMAIL = '';
const ACCESS_TOKEN = '';

class InstariseDB {
    public $acciones = 'acciones_instagram';
    protected $mysqli;
    protected $stmt = NULL;

    function __construct($hostname, $username, $password, $database) {
        $this->mysqli = new mysqli($hostname, $username, $password, $database);
        if ( ! $this->mysqli ) throw new Exception("Hubo un problema conectandose con la base de datos");
    }

    function __destruct() {
        if ( $this->stmt ) $this->stmt->close();
        if ( $this->mysqli ) $this->mysqli->close();
    }

    public function insert( $accion ) {
        $sql = "INSERT INTO `{$this->acciones}` (`ig_id`, `fecha`, `urls`, `descripcion`, `pub_url`) 
        VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
        fecha = VALUES(fecha),
        urls = VALUES(urls), 
        descripcion = VALUES(descripcion),
        pub_url = VALUES(pub_url)";

        ! $this->stmt and $this->stmt = $this->mysqli->prepare($sql);

        if ( $this->stmt ) {
            $this->stmt->bind_param('sssss', $ig_id, $fecha, $urls, $descripcion, $pub_url);
            extract($accion);
            return $this->stmt->execute();
        }

        return NULL;
    }

    public function last_error_as_array() {
        if ( $this->mysqli || $this->stmt )
            return 
                $this->mysqli->errno ? array( 'errno' => $this->mysqli->errno, 'error' => $this->mysqli->error ) :
                ($this->stmt->errno ? array( 'errno' => $this->stmt->errno, 'error' => $this->stmt->error ) : 0);
        return NULL;
    }

    public function select( $fecha ) {
        $sql = "SELECT ig_id, fecha, urls, descripcion, pub_url FROM `{$this->acciones}` WHERE fecha = ?";

        if ( ! $this->stmt )
            $this->stmt = $this->mysqli->prepare($sql);

        if ($this->stmt ) {
            $this->stmt->bind_param('s', $fecha);
            if ($this->stmt->execute()) {
                $this->stmt->bind_result($ig_id, $fecha, $urls, $descripcion, $pub_url);
                $acciones = array();
                while ( $this->stmt->fetch() )
                    $acciones[] = compact('ig_id', 'fecha', 'urls', 'descripcion', 'pub_url');
                
                $this->stmt->close();
                return $acciones;
            }
        }
        return NULL;
    }

}