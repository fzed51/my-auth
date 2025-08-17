#!/usr/bin/env php
<?php

/**
 * Script utilitaire pour scanner et reformater automatiquement tous les fichiers
 * avec des lignes trop longues
 */

function main(): void
{
    $options = getopt('d:h', ['directory:', 'help', 'dry-run', 'no-backup']);
    
    if (isset($options['h']) || isset($options['help'])) {
        showHelp();
        return;
    }

    $directory = $options['d'] ?? $options['directory'] ?? getcwd();
    $dryRun = isset($options['dry-run']);
    $noBackup = isset($options['no-backup']);
    
    if (!is_dir($directory)) {
        echo "❌ Erreur : Le répertoire '$directory' n'existe pas.\n";
        exit(1);
    }

    echo "🔄 Processus automatique de formatage des fichiers PHP\n";
    echo "📁 Répertoire : $directory\n";
    echo "📏 Limite : 80 caractères par ligne\n";
    
    if ($dryRun) {
        echo "🔍 Mode test (dry-run) - aucune modification ne sera effectuée\n";
    }
    echo "\n";

    // Étape 1 : Scanner les fichiers
    echo "📋 Étape 1 : Scan des fichiers avec des lignes trop longues...\n";
    
    $command = "php " . __DIR__ . "/scan-long-lines.php --files-only -d " . escapeshellarg($directory) . " 2>/dev/null";
    $output = shell_exec($command);
    
    if (empty($output)) {
        echo "✅ Aucun fichier à reformater trouvé.\n";
        return;
    }
    
    $filesToFormat = array_filter(explode("\n", trim($output)), function($line) {
        // Filtrer seulement les lignes qui ressemblent à des chemins de fichiers
        return !empty($line) && strpos($line, '.php') !== false && strpos($line, '/') !== false;
    });
    
    if (empty($filesToFormat)) {
        echo "✅ Aucun fichier à reformater trouvé.\n";
        return;
    }
    
    echo "📊 " . count($filesToFormat) . " fichier(s) à reformater trouvé(s) :\n";
    foreach ($filesToFormat as $file) {
        echo "   - $file\n";
    }
    echo "\n";
    
    if ($dryRun) {
        echo "🔍 Mode test activé - arrêt ici.\n";
        echo "💡 Relancez sans --dry-run pour effectuer le formatage.\n";
        return;
    }
    
    // Étape 2 : Reformater chaque fichier
    echo "🔧 Étape 2 : Formatage des fichiers...\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($filesToFormat as $file) {
        echo "🔄 Formatage de : $file\n";
        
        $formatCommand = "php -d error_reporting=\"E_ALL&~E_DEPRECATED\" " . __DIR__ . "/format-php.php -f " . escapeshellarg($file) . " --in-place";
        
        if ($noBackup) {
            $formatCommand .= " --no-backup";
        }
        
        $result = shell_exec($formatCommand . " 2>&1");
        
        if (strpos($result, "✅") !== false) {
            $successCount++;
            echo "   ✅ Succès\n";
        } else {
            $errorCount++;
            echo "   ❌ Erreur : $result\n";
        }
        echo "\n";
    }
    
    // Résumé
    echo "📊 Résumé du formatage :\n";
    echo "   ✅ Fichiers formatés avec succès : $successCount\n";
    echo "   ❌ Fichiers en erreur : $errorCount\n";
    echo "   📁 Total traité : " . count($filesToFormat) . "\n\n";
    
    if ($successCount > 0) {
        echo "🎉 Formatage terminé avec succès !\n";
        
        if (!$noBackup) {
            echo "💾 Les fichiers originaux ont été sauvegardés (.backup).\n";
        }
        
        echo "💡 Vous pouvez maintenant vérifier les modifications avec git diff.\n";
    }
}

function showHelp(): void
{
    echo "Usage: php bin/auto-format.php [OPTIONS]\n\n";
    echo "Ce script combine scan-long-lines.php et format-php.php pour reformater\n";
    echo "automatiquement tous les fichiers PHP avec des lignes trop longues.\n\n";
    echo "Options :\n";
    echo "  -d, --directory <dir>  Répertoire à scanner et reformater (défaut: répertoire courant)\n";
    echo "  --dry-run             Mode test - affiche les fichiers à traiter sans les modifier\n";
    echo "  --no-backup           Ne crée pas de backup des fichiers originaux\n";
    echo "  -h, --help            Affiche cette aide\n\n";
    echo "Exemples :\n";
    echo "  php bin/auto-format.php\n";
    echo "  php bin/auto-format.php -d src/\n";
    echo "  php bin/auto-format.php --dry-run\n";
    echo "  php bin/auto-format.php --no-backup\n";
}

main();
