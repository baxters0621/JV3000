<?php

// ==========================================
// CLASE SINGLETON DE BASE DE DATOS
// ==========================================
class Database
{
    // Instancia singleton y estado de conexión
    private static ?self $instance = null;
    private ?mysqli $conn = null;
    private int $transactionDepth = 0;

    // Constructor privado
    private function __construct() {}

    // Obtener instancia singleton
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Conectar a MySQL
    public function connect(string $host, string $user, string $pass, string $db): void
    {
        $this->conn = new mysqli($host, $user, $pass, $db);
        if ($this->conn->connect_error) {
            throw new RuntimeException('Error de conexión: ' . $this->conn->connect_error);
        }
        $this->conn->set_charset('utf8mb4');
        $this->conn->autocommit(true);
    }

    // Obtener conexión mysqli
    public function getConnection(): ?mysqli
    {
        return $this->conn;
    }

    // Ejecutar consulta SQL
    public function execute(string $sql, array $params = []): mysqli_stmt
    {
        if (!$this->conn) {
            throw new RuntimeException('Base de datos no conectada.');
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Error preparando consulta: ' . $this->conn->error . ' SQL: ' . $sql);
        }

        if (!empty($params)) {
            $types = '';
            $bindValues = [];
            foreach ($params as $p) {
                if (is_int($p)) {
                    $types .= 'i';
                } elseif (is_float($p)) {
                    $types .= 'd';
                } elseif (is_null($p)) {
                    $types .= 's';
                    $p = null;
                } else {
                    $types .= 's';
                }
                $bindValues[] = $p;
            }

            if (!empty($bindValues)) {
                $stmt->bind_param($types, ...$bindValues);
            }
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('Error ejecutando consulta: ' . $stmt->error);
        }

        return $stmt;
    }

    // Obtener una fila
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $result->free();
        $stmt->close();
        return $row ?: null;
    }

    // Obtener todas las filas
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        $stmt->close();
        return $rows;
    }

    // Insertar fila
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->execute($sql, array_values($data));
        $insertId = (int)$stmt->insert_id;
        $stmt->close();
        return $insertId;
    }

    // Actualizar filas
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$sets} WHERE {$where}";
        $stmt = $this->execute($sql, array_merge(array_values($data), $whereParams));
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    // Iniciar transacción
    public function begin(): void
    {
        if ($this->transactionDepth === 0) {
            $this->conn->begin_transaction();
        }
        $this->transactionDepth++;
    }

    // Confirmar transacción
    public function commit(): void
    {
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->conn->commit();
        }
    }

    // Revertir transacción
    public function rollback(): void
    {
        $this->transactionDepth = 0;
        $this->conn->rollback();
    }

    // Verificar si hay transacción activa
    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    // Último ID insertado
    public function lastInsertId(): int
    {
        return (int)$this->conn->insert_id;
    }
}
