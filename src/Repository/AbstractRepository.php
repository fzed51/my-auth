<?php

declare(strict_types=1);

namespace MyAuth\Repository;

use PDO;
use PDOStatement;
use InvalidArgumentException;

/**
 * Classe de base abstraite pour tous les repositories
 * 
 * Fournit les fonctionnalités communes pour l'accès aux données :
 * - Gestion de la connexion PDO
 * - Méthodes de base pour les opérations CRUD
 * - Gestion des erreurs et logging
 * - Validation des paramètres
 * 
 * @package MyAuth\Repository
 */
abstract class AbstractRepository
{
    protected PDO $pdo;
    protected string $tableName;
    protected string $primaryKey = 'id';

    /**
     * Constructeur du repository
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->validateTableName();
    }

    /**
     * Validation du nom de table (doit être défini dans les classes enfants)
     */
    private function validateTableName(): void
    {
        if (empty($this->tableName)) {
            throw new InvalidArgumentException(
                'La propriété $tableName doit être définie dans ' . static::class
            );
        }
    }

    /**
     * Recherche un enregistrement par son ID
     */
    public function findById(string $id): ?array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Recherche des enregistrements selon des critères
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null): array
    {
        $whereClause = $this->buildWhereClause($criteria);
        $orderClause = $this->buildOrderClause($orderBy);
        $limitClause = $limit ? "LIMIT {$limit}" : '';

        $sql = "SELECT * FROM {$this->tableName} {$whereClause} {$orderClause} {$limitClause}";
        $stmt = $this->pdo->prepare($sql);
        
        $this->bindCriteriaValues($stmt, $criteria);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recherche un seul enregistrement selon des critères
     */
    public function findOneBy(array $criteria): ?array
    {
        $results = $this->findBy($criteria, [], 1);
        return $results[0] ?? null;
    }

    /**
     * Récupère tous les enregistrements de la table
     */
    public function findAll(array $orderBy = [], ?int $limit = null): array
    {
        return $this->findBy([], $orderBy, $limit);
    }

    /**
     * Compte le nombre d'enregistrements selon des critères
     */
    public function count(array $criteria = []): int
    {
        $whereClause = $this->buildWhereClause($criteria);
        $sql = "SELECT COUNT(*) FROM {$this->tableName} {$whereClause}";
        
        $stmt = $this->pdo->prepare($sql);
        $this->bindCriteriaValues($stmt, $criteria);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Vérifie si un enregistrement existe selon des critères
     */
    public function exists(array $criteria): bool
    {
        return $this->count($criteria) > 0;
    }

    /**
     * Insère un nouvel enregistrement
     */
    public function insert(array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->tableName,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $this->bindDataValues($stmt, $data);
        $stmt->execute();

        return $this->pdo->lastInsertId() ?: '';
    }

    /**
     * Met à jour un enregistrement par son ID
     */
    public function update(string $id, array $data): bool
    {
        $setClause = $this->buildSetClause($data);
        $sql = "UPDATE {$this->tableName} SET {$setClause} WHERE {$this->primaryKey} = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $this->bindDataValues($stmt, $data);

        return $stmt->execute();
    }

    /**
     * Met à jour des enregistrements selon des critères
     */
    public function updateBy(array $criteria, array $data): int
    {
        $setClause = $this->buildSetClause($data);
        $whereClause = $this->buildWhereClause($criteria);
        
        $sql = "UPDATE {$this->tableName} SET {$setClause} {$whereClause}";

        $stmt = $this->pdo->prepare($sql);
        $this->bindDataValues($stmt, $data);
        $this->bindCriteriaValues($stmt, $criteria);

        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Supprime un enregistrement par son ID
     */
    public function delete(string $id): bool
    {
        $sql = "DELETE FROM {$this->tableName} WHERE {$this->primaryKey} = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Supprime des enregistrements selon des critères
     */
    public function deleteBy(array $criteria): int
    {
        $whereClause = $this->buildWhereClause($criteria);
        $sql = "DELETE FROM {$this->tableName} {$whereClause}";

        $stmt = $this->pdo->prepare($sql);
        $this->bindCriteriaValues($stmt, $criteria);

        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Exécute une requête SQL personnalisée
     */
    protected function query(string $sql, array $parameters = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($parameters as $key => $value) {
            $stmt->bindValue($key, $value, $this->getPdoType($value));
        }
        
        $stmt->execute();
        return $stmt;
    }

    /**
     * Démarre une transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Valide une transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Annule une transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollback();
    }

    /**
     * Vérifie si une transaction est active
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Construction de la clause WHERE
     */
    private function buildWhereClause(array $criteria): string
    {
        if (empty($criteria)) {
            return '';
        }

        $conditions = [];
        foreach (array_keys($criteria) as $column) {
            $conditions[] = "{$column} = :{$column}";
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }

    /**
     * Construction de la clause ORDER BY
     */
    private function buildOrderClause(array $orderBy): string
    {
        if (empty($orderBy)) {
            return '';
        }

        $orderParts = [];
        foreach ($orderBy as $column => $direction) {
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $orderParts[] = "{$column} {$direction}";
        }

        return 'ORDER BY ' . implode(', ', $orderParts);
    }

    /**
     * Construction de la clause SET pour UPDATE
     */
    private function buildSetClause(array $data): string
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }

        return implode(', ', $setParts);
    }

    /**
     * Liaison des valeurs des critères
     */
    private function bindCriteriaValues(PDOStatement $stmt, array $criteria): void
    {
        foreach ($criteria as $column => $value) {
            $stmt->bindValue(":{$column}", $value, $this->getPdoType($value));
        }
    }

    /**
     * Liaison des valeurs des données
     */
    private function bindDataValues(PDOStatement $stmt, array $data): void
    {
        foreach ($data as $column => $value) {
            $stmt->bindValue(":{$column}", $value, $this->getPdoType($value));
        }
    }

    /**
     * Détermine le type PDO approprié pour une valeur
     */
    private function getPdoType($value): int
    {
        return match (gettype($value)) {
            'boolean' => PDO::PARAM_BOOL,
            'integer' => PDO::PARAM_INT,
            'NULL' => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * Getter pour accéder à la connexion PDO (pour les requêtes complexes)
     */
    protected function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Getter pour le nom de la table
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Getter pour la clé primaire
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }
}
