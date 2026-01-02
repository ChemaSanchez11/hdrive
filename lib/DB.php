<?php

class DB
{
    private static ?DB $instance = null;
    private PDO $pdo;

    private function __construct(array $config)
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['dbname'],
                $config['charset'] ?? 'utf8mb4'
            );

            $this->pdo = new PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

        } catch (PDOException $e) {
            throw new Exception(
                'Error conectando con la base de datos',
                500,
                $e
            );
        }
    }

    /* =====================
       Singleton
       ===================== */

    public static function getInstance(array $config): DB
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /* =====================
       SELECT múltiple
       ===================== */

    public function query(string $sql, array $params = []): array
    {
        try {
            $this->validateParams($sql, $params);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            throw new Exception(
                'Error ejecutando consulta',
                500,
                $e
            );
        }
    }

    /* =====================
       SELECT único
       ===================== */

    public function fetch(string $sql, array $params = []): ?object
    {
        try {
            $this->validateParams($sql, $params);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $res = $stmt->fetch();
            return $res === false ? null : $res;

        } catch (PDOException $e) {
            throw new Exception(
                'Error obteniendo registro',
                500,
                $e
            );
        }
    }

    /* =====================
       INSERT / UPDATE / DELETE
       ===================== */

    public function execute(string $sql, array $params = []): int
    {
        try {
            $this->validateParams($sql, $params);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount();

        } catch (PDOException $e) {
            throw new Exception(
                'Error ejecutando escritura en BD',
                500,
                $e
            );
        }
    }

    public function lastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    /* =====================
       Transacciones (Moodle-like)
       ===================== */

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /* =====================
       VALIDACIÓN DE PARÁMETROS
       ===================== */

    private function validateParams(string $sql, array $params): void
    {
        // Detectar placeholders tipo :id :name etc
        preg_match_all('/:([a-zA-Z0-9_]+)/', $sql, $matches);
        $placeholders = $matches[1];

        foreach ($placeholders as $ph) {
            if (!array_key_exists($ph, $params)) {
                throw new Exception(
                    "Falta el parámetro obligatorio :$ph",
                    400
                );
            }
        }
    }
}