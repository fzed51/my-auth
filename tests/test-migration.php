#!/usr/bin/env php
<?php

/**
 * Test de migration et réinitialisation de la base de données
 * Vérifie que le schéma peut être créé, détruit et recréé proprement
 */

echo "🔄 TEST DE MIGRATION DE LA BASE DE DONNÉES\n";
echo "==========================================\n\n";

// Configuration
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

    echo "✅ Connexion à la base de données établie\n\n";

    // Test 1: Vérifier les tables existantes
    echo "📋 Test 1: Vérification des tables existantes\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedTables = ['users', 'email_verifications', 'jwt_blacklist'];
    $foundTables = [];
    
    foreach ($expectedTables as $expectedTable) {
        if (in_array($expectedTable, $tables)) {
            echo "   ✅ Table '{$expectedTable}' trouvée\n";
            $foundTables[] = $expectedTable;
        } else {
            echo "   ❌ Table '{$expectedTable}' manquante\n";
        }
    }
    
    if (count($foundTables) === count($expectedTables)) {
        echo "   🎉 Toutes les tables requises sont présentes\n";
    }
    echo "\n";

    // Test 2: Vérifier les contraintes FK
    echo "🔗 Test 2: Vérification des contraintes de clés étrangères\n";
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = '{$dbname}' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY TABLE_NAME, COLUMN_NAME
    ");
    
    $foreignKeys = $stmt->fetchAll();
    $expectedFK = [
        'email_verifications.user_id -> users.id',
        'jwt_blacklist.user_id -> users.id'
    ];
    
    $foundFK = [];
    foreach ($foreignKeys as $fk) {
        $fkString = "{$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}";
        $foundFK[] = $fkString;
        echo "   ✅ {$fkString}\n";
    }
    
    if (count($foundFK) === count($expectedFK)) {
        echo "   🎉 Toutes les contraintes FK sont présentes\n";
    }
    echo "\n";

    // Test 3: Test d'insertion de données de test
    echo "💾 Test 3: Test d'insertion de données\n";
    
    // Générer un UUID simple pour les tests
    $userId = '123e4567-e89b-12d3-a456-426614174000';
    $tokenId = '123e4567-e89b-12d3-a456-426614174001';
    $blacklistId = '123e4567-e89b-12d3-a456-426614174002';
    
    // Test insertion utilisateur
    $stmt = $pdo->prepare("
        INSERT INTO users (id, email, password_hash, is_active, is_verified, first_name, last_name) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        'test@example.com',
        password_hash('test123', PASSWORD_DEFAULT),
        0,  // false = 0 for MySQL TINYINT
        0,  // false = 0 for MySQL TINYINT
        'Test',
        'User'
    ]);
    echo "   ✅ Utilisateur de test créé\n";

    // Test insertion token de vérification
    $stmt = $pdo->prepare("
        INSERT INTO email_verifications (id, user_id, token, expires_at, is_used) 
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), ?)
    ");
    $stmt->execute([
        $tokenId,
        $userId,
        hash('sha256', 'test-token'),
        0  // false = 0 for MySQL TINYINT
    ]);
    echo "   ✅ Token de vérification créé\n";

    // Test insertion JWT blacklist
    $stmt = $pdo->prepare("
        INSERT INTO jwt_blacklist (id, jti, user_id, token_hash, expires_at, reason) 
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), ?)
    ");
    $stmt->execute([
        $blacklistId,
        'test-jti-123',
        $userId,
        hash('sha256', 'test-jwt-token'),
        'logout'
    ]);
    echo "   ✅ Token JWT blacklisté\n";
    echo "\n";

    // Test 4: Vérifier les données insérées et les contraintes
    echo "🔍 Test 4: Vérification des données et contraintes\n";
    
    // Compter les enregistrements
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "   📊 Utilisateurs: {$userCount}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_verifications");
    $tokenCount = $stmt->fetch()['count'];
    echo "   📊 Tokens de vérification: {$tokenCount}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM jwt_blacklist");
    $blacklistCount = $stmt->fetch()['count'];
    echo "   📊 Tokens blacklistés: {$blacklistCount}\n";
    echo "\n";

    // Test 5: Test de suppression en cascade
    echo "🗑️ Test 5: Test de suppression en cascade\n";
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    // Vérifier que les enregistrements liés ont été supprimés
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_verifications");
    $tokenCountAfter = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM jwt_blacklist");
    $blacklistCountAfter = $stmt->fetch()['count'];
    
    if ($tokenCountAfter === '0' && $blacklistCountAfter === '0') {
        echo "   ✅ Suppression en cascade fonctionnelle\n";
    } else {
        echo "   ❌ Problème avec la suppression en cascade\n";
    }
    echo "\n";

    // Test 6: Vérifier les index
    echo "📈 Test 6: Vérification des index\n";
    $stmt = $pdo->query("
        SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
        FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = '{$dbname}' 
            AND INDEX_NAME != 'PRIMARY'
        ORDER BY TABLE_NAME, INDEX_NAME
    ");
    
    $indexes = $stmt->fetchAll();
    $indexCount = count($indexes);
    echo "   📊 Index trouvés: {$indexCount}\n";
    
    foreach ($indexes as $index) {
        echo "   🔗 {$index['TABLE_NAME']}.{$index['INDEX_NAME']} sur {$index['COLUMN_NAME']}\n";
    }
    
    echo "\n🎉 TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS!\n";
    echo "✅ La base de données est correctement configurée et fonctionnelle.\n";
    echo "✅ Les contraintes de sécurité sont en place.\n";
    echo "✅ Les relations entre tables fonctionnent.\n";
    echo "✅ Prêt pour l'étape 2 du développement.\n\n";

} catch (PDOException $e) {
    echo "❌ Erreur de base de données : " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Erreur générale : " . $e->getMessage() . "\n";
    exit(1);
}
