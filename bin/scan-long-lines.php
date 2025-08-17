#!/usr/bin/env php
<?php

/**
 * Script pour scanner les fichiers PHP et détecter ceux avec des lignes de plus de 80 caractères
 */

class LongLineScanner
{
    private const MAX_LINE_LENGTH = 80;
    private array $excludePatterns = [
        '/vendor/',
        '/var/',
        '/cache/',
        '/logs/',
        '/node_modules/',
        '.git',
    ];

    public function scanDirectory(string $directory): array
    {
        $filesWithLongLines = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getPathname();
            
            // Exclure certains répertoires
            if ($this->shouldExcludeFile($filePath)) {
                continue;
            }

            $longLines = $this->checkFileForLongLines($filePath);
            if (!empty($longLines)) {
                $filesWithLongLines[$filePath] = $longLines;
            }
        }

        return $filesWithLongLines;
    }

    private function shouldExcludeFile(string $filePath): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (strpos($filePath, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private function checkFileForLongLines(string $filePath): array
    {
        $longLines = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        
        if ($lines === false) {
            return $longLines;
        }

        foreach ($lines as $lineNumber => $line) {
            if (mb_strlen($line) > self::MAX_LINE_LENGTH) {
                $longLines[] = [
                    'line' => $lineNumber + 1,
                    'length' => mb_strlen($line),
                    'content' => mb_substr($line, 0, 100) . (mb_strlen($line) > 100 ? '...' : '')
                ];
            }
        }

        return $longLines;
    }

    public function displayResults(array $filesWithLongLines): void
    {
        if (empty($filesWithLongLines)) {
            echo "✅ Aucun fichier avec des lignes dépassant " . self::MAX_LINE_LENGTH . " caractères trouvé.\n";
            return;
        }

        echo "🔍 Fichiers avec des lignes dépassant " . self::MAX_LINE_LENGTH . " caractères :\n\n";
        
        foreach ($filesWithLongLines as $filePath => $longLines) {
            echo "📁 " . $filePath . "\n";
            foreach ($longLines as $lineInfo) {
                echo sprintf(
                    "   Ligne %d (%d caractères): %s\n",
                    $lineInfo['line'],
                    $lineInfo['length'],
                    $lineInfo['content']
                );
            }
            echo "\n";
        }

        echo "💡 Utilisez le script de reformatage :\n";
        echo "   php bin/format-php.php <fichier>\n\n";
        
        echo "📊 Résumé :\n";
        echo "   - " . count($filesWithLongLines) . " fichier(s) à reformater\n";
        $totalLongLines = array_sum(array_map('count', $filesWithLongLines));
        echo "   - " . $totalLongLines . " ligne(s) trop longue(s) au total\n";
    }

    public function getFilesToFormat(array $filesWithLongLines): array
    {
        return array_keys($filesWithLongLines);
    }
}

// Script principal
function main(): void
{
    $options = getopt('d:h', ['directory:', 'help', 'files-only']);
    
    if (isset($options['h']) || isset($options['help'])) {
        showHelp();
        return;
    }

    $directory = $options['d'] ?? $options['directory'] ?? getcwd();
    
    if (!is_dir($directory)) {
        echo "❌ Erreur : Le répertoire '$directory' n'existe pas.\n";
        exit(1);
    }

    echo "🔍 Scan des fichiers PHP dans : $directory\n";
    echo "📏 Limite de longueur : 80 caractères\n\n";

    $scanner = new LongLineScanner();
    $filesWithLongLines = $scanner->scanDirectory($directory);
    
    if (isset($options['files-only'])) {
        // Mode pour obtenir seulement la liste des fichiers (utile pour les scripts)
        $filesToFormat = $scanner->getFilesToFormat($filesWithLongLines);
        foreach ($filesToFormat as $file) {
            echo $file . "\n";
        }
    } else {
        $scanner->displayResults($filesWithLongLines);
    }
}

function showHelp(): void
{
    echo "Usage: php bin/scan-long-lines.php [OPTIONS]\n\n";
    echo "Options :\n";
    echo "  -d, --directory <dir>  Répertoire à scanner (défaut: répertoire courant)\n";
    echo "  --files-only          Affiche seulement la liste des fichiers (sans détails)\n";
    echo "  -h, --help            Affiche cette aide\n\n";
    echo "Exemples :\n";
    echo "  php bin/scan-long-lines.php\n";
    echo "  php bin/scan-long-lines.php -d src/\n";
    echo "  php bin/scan-long-lines.php --files-only\n";
}

main();
