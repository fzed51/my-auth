#!/usr/bin/env php
<?php

/**
 * Script de test pour la base de données
 * Vérifie que les tables ont été créées correctement
 */

// Configuration de la base de données
$host = '127.0.0.1';
$port = 3306;
$dbname = 'my_auth';
$username = 'auth_user';
$password = 'auth_password';

try {
    // Connexion à la base de données
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✅ Connexion à la base de données réussie\n\n";

    // Vérifier les tables existantes
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Tables trouvées :\n";
    foreach ($tables as $table) {
        echo "  - {$table}\n";
    }
    echo "\n";

    // Vérifier la structure de chaque table
    $expectedTables = ['users', 'email_verifications', 'jwt_blacklist'];
    
    foreach ($expectedTables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ Table '{$table}' existe\n";
            
            // Afficher la structure
            $stmt = $pdo->query("DESCRIBE {$table}");
            $columns = $stmt->fetchAll();
            
            echo "  Colonnes :\n";
            foreach ($columns as $column) {
                echo "    - {$column['Field']} ({$column['Type']}) {$column['Key']}\n";
            }
            echo "\n";
        } else {
            echo "❌ Table '{$table}' manquante\n";
        }
    }

    // Vérifier les clés étrangères
    echo "🔗 Vérification des clés étrangères :\n";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = '{$dbname}' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $foreignKeys = $stmt->fetchAll();
    foreach ($foreignKeys as $fk) {
        echo "  - {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
    }

    echo "\n✅ Test de la base de données terminé avec succès!\n";

} catch (PDOException $e) {
    echo "❌ Erreur de connexion : " . $e->getMessage() . "\n";
    exit(1);
}
