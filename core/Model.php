<?php

// ==========================================
// MODEL BASE — Los datos
// ==========================================
// Es el único que habla con la base de datos.
// No imprime HTML, no usa $_POST/$_GET y no
// sabe qué pantalla verá el usuario.

/**
 * Model: clase base de la que heredan todos los modelos.
 *
 * Representa la capa de datos del patrón MVC: es la única autorizada para
 * consultar la base de datos. No imprime HTML ni depende de la petición web.
 */
abstract class Model
{
    protected Database $db;

    /**
     * Constructor: obtiene la instancia única de la base de datos.
     *
     * Todos los modelos comparten la misma conexión (singleton Database)
     * a través de la propiedad protegida $db.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
}
