<?php

// ==========================================
// MODEL BASE — Los datos
// ==========================================
// Es el único que habla con la base de datos.
// No imprime HTML, no usa $_POST/$_GET y no
// sabe qué pantalla verá el usuario.
abstract class Model
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }
}
