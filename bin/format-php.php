#!/usr/bin/env php
<?php
/**
 * Formateur PHP PSR-12 basé sur l'AST
 * 
 * Utilise nikic/php-parser pour analyser et reformater le code PHP
 * selon les standards PSR-12 avec une limite de 80 caractères par ligne.
 * 
 * Usage: php format-php-ast.php -f fichier.php
 *        php format-php-ast.php -h
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Error;

class PSR12ASTFormatter
{
    private int $maxLineLength = 80;
    private PSR12PrettyPrinter $printer;
    
    public function __construct()
    {
        $this->printer = new PSR12PrettyPrinter([
            'shortArraySyntax' => true,
        ]);
    }
    
    /**
     * Formate le code PHP selon PSR-12
     */
    public function format(string $code): string
    {
        try {
            $parser = (new ParserFactory)->createForNewestSupportedVersion();
            $ast = $parser->parse($code);
            
            if ($ast === null) {
                throw new \RuntimeException('Impossible de parser le code PHP');
            }
            
            // Traverser l'AST pour appliquer les règles PSR-12
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new PSR12NodeVisitor());
            
            $ast = $traverser->traverse($ast);
            
            // Générer le code formaté
            $formattedCode = $this->printer->prettyPrintFile($ast);
            
            // Post-traitement pour respecter la limite de 80 caractères
            return $this->enforceLineLength($formattedCode);
            
        } catch (Error $e) {
            throw new \RuntimeException('Erreur de parsing PHP: ' . $e->getMessage());
        }
    }
    
    /**
     * Applique la limite de 80 caractères par ligne
     */
    private function enforceLineLength(string $code): string
    {
        $lines = explode("\n", $code);
        $result = [];
        
        foreach ($lines as $line) {
            if (strlen($line) <= $this->maxLineLength) {
                $result[] = $line;
                continue;
            }
            
            // Essayer de casser la ligne intelligemment
            $broken = $this->breakLongLine($line);
            $result = array_merge($result, $broken);
        }
        
        return implode("\n", $result);
    }
    
    /**
     * Casse une ligne longue de manière intelligente
     */
    private function breakLongLine(string $line): array
    {
        // Détecter l'indentation
        preg_match('/^(\s*)/', $line, $matches);
        $indent = $matches[1];
        $extraIndent = '    '; // 4 espaces supplémentaires pour les continuations
        
        // Points de cassure préférentiels
        $breakPoints = [
            ', ',     // Paramètres de fonction
            ' && ',   // Opérateurs logiques
            ' || ',   
            ' . ',    // Concaténation
            '->',     // Chaînage de méthodes
            ' = ',    // Assignation
        ];
        
        foreach ($breakPoints as $breakPoint) {
            if (strpos($line, $breakPoint) !== false) {
                return $this->breakAtPoint($line, $breakPoint, $indent, $extraIndent);
            }
        }
        
        // Cassure par défaut à 80 caractères
        return [
            substr($line, 0, $this->maxLineLength - 3) . '...',
            $indent . $extraIndent . substr($line, $this->maxLineLength - 3)
        ];
    }
    
    /**
     * Casse une ligne à un point spécifique
     */
    private function breakAtPoint(string $line, string $breakPoint, string $indent, string $extraIndent): array
    {
        $parts = explode($breakPoint, $line);
        $result = [];
        $currentLine = $parts[0] . $breakPoint;
        
        for ($i = 1; $i < count($parts); $i++) {
            $part = $parts[$i];
            
            if (strlen($currentLine . $part) > $this->maxLineLength) {
                $result[] = rtrim($currentLine);
                $currentLine = $indent . $extraIndent . $part;
            } else {
                $currentLine .= $part;
            }
            
            if ($i < count($parts) - 1) {
                $currentLine .= $breakPoint;
            }
        }
        
        $result[] = $currentLine;
        return $result;
    }
}

/**
 * Visitor pour appliquer les règles PSR-12 spécifiques
 */
class PSR12NodeVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node)
    {
        // Normaliser les espaces dans les commentaires
        if ($node instanceof Node\Stmt\Nop && !empty($node->getComments())) {
            foreach ($node->getComments() as $comment) {
                // Préserver les commentaires mais normaliser l'indentation
            }
        }
        
        return $node;
    }
}

/**
 * PrettyPrinter personnalisé pour PSR-12
 */
class PSR12PrettyPrinter extends PrettyPrinter\Standard
{
    protected function pStmt_Class(Node\Stmt\Class_ $node): string
    {
        return $this->pClassCommon($node, ' ' . $node->name);
    }
    
    protected function pStmt_ClassMethod(Node\Stmt\ClassMethod $node): string
    {
        return $this->pAttrGroups($node->attrGroups ?? [])
             . $this->pModifiers($node->flags)
             . 'function ' . ($node->byRef ? '&' : '') . $node->name
             . '(' . $this->pCommaSeparated($node->params) . ')'
             . (null !== $node->returnType ? ': ' . $this->p($node->returnType) : '')
             . (null !== $node->stmts
                ? "\n" . '{' . $this->pStmts($node->stmts) . "\n" . '}'
                : ';');
    }
    
    protected function pStmt_Function(Node\Stmt\Function_ $node): string
    {
        return $this->pAttrGroups($node->attrGroups ?? [])
             . 'function ' . ($node->byRef ? '&' : '') . $node->name
             . '(' . $this->pCommaSeparated($node->params) . ')'
             . (null !== $node->returnType ? ': ' . $this->p($node->returnType) : '')
             . "\n" . '{' . $this->pStmts($node->stmts) . "\n" . '}';
    }
    
    protected function pStmt_If(Node\Stmt\If_ $node): string
    {
        return 'if (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->stmts) . "\n" . '}'
             . ($node->elseifs ? ' ' . $this->pImplode($node->elseifs, ' ') : '')
             . (null !== $node->else ? ' ' . $this->p($node->else) : '');
    }
    
    protected function pStmt_ElseIf(Node\Stmt\ElseIf_ $node): string
    {
        return 'elseif (' . $this->p($node->cond) . ') {'
             . $this->pStmts($node->stmts) . "\n" . '}';
    }
    
    protected function pStmt_Else(Node\Stmt\Else_ $node): string
    {
        return 'else {' . $this->pStmts($node->stmts) . "\n" . '}';
    }
}

/**
 * Fonction principale du script
 */
function main(): int
{
    $options = getopt('f:h', ['file:', 'help']);
    
    if (isset($options['h']) || isset($options['help'])) {
        showHelp();
        return 0;
    }
    
    $file = $options['f'] ?? $options['file'] ?? null;
    
    if (!$file) {
        echo "Erreur: Fichier requis. Utilisez -f ou --file\n";
        showHelp();
        return 1;
    }
    
    if (!file_exists($file)) {
        echo "Erreur: Le fichier '$file' n'existe pas.\n";
        return 1;
    }
    
    if (!is_readable($file)) {
        echo "Erreur: Le fichier '$file' n'est pas lisible.\n";
        return 1;
    }
    
    try {
        echo "Formatage PSR-12 de: $file\n";
        
        // Lire le fichier original
        $originalCode = file_get_contents($file);
        if ($originalCode === false) {
            throw new \RuntimeException("Impossible de lire le fichier: $file");
        }
        
        // Créer une sauvegarde
        $backupFile = $file . '.backup-' . date('Y-m-d-H-i-s');
        if (!copy($file, $backupFile)) {
            throw new \RuntimeException("Impossible de créer la sauvegarde: $backupFile");
        }
        echo "Sauvegarde créée: $backupFile\n";
        
        // Formater le code
        $formatter = new PSR12ASTFormatter();
        $formattedCode = $formatter->format($originalCode);
        
        // Écrire le fichier formaté
        if (file_put_contents($file, $formattedCode) === false) {
            throw new \RuntimeException("Impossible d'écrire le fichier formaté: $file");
        }
        
        echo "✅ Fichier formaté avec succès selon PSR-12\n";
        
        // Statistiques
        $originalLines = substr_count($originalCode, "\n") + 1;
        $formattedLines = substr_count($formattedCode, "\n") + 1;
        echo "Lignes originales: $originalLines\n";
        echo "Lignes formatées: $formattedLines\n";
        
        return 0;
        
    } catch (\Throwable $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
        return 1;
    }
}

function showHelp(): void
{
    echo <<<HELP
Formateur PHP PSR-12 avec limite de 80 caractères (basé sur AST)

USAGE:
    php format-php.php -f fichier.php
    php format-php.php --file fichier.php

OPTIONS:
    -f, --file      Fichier PHP à formater
    -h, --help      Afficher cette aide

EXEMPLE:
    php format-php.php -f src/Controller/AuthController.php

Le script crée automatiquement une sauvegarde avant formatage.

HELP;
}

// Exécution du script
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    exit(main());
}
