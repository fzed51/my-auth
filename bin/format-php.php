#!/usr/bin/env php
<?php

/**
 * Script pour reformater un fichier PHP en utilisant les tokens
 * pour respecter la limite de 80 caractères par ligne
 */

class PHPFileFormatter
{
    private const MAX_LINE_LENGTH = 80;
    private const INDENT_SIZE = 4;
    
    private string $currentLine = '';
    private int $indentLevel = 0;
    private array $formattedLines = [];
    private bool $inString = false;
    private string $stringDelimiter = '';
    
    public function formatFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Le fichier '$filePath' n'existe pas.");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Impossible de lire le fichier '$filePath'.");
        }

        return $this->formatContent($content);
    }

    public function formatContent(string $content): string
    {
        $this->reset();
        $tokens = token_get_all($content);
        
        foreach ($tokens as $token) {
            $this->processToken($token);
        }
        
        // Ajouter la dernière ligne si elle n'est pas vide
        if (!empty(trim($this->currentLine))) {
            $this->addLine();
        }
        
        return implode("\n", $this->formattedLines);
    }

    private function reset(): void
    {
        $this->currentLine = '';
        $this->indentLevel = 0;
        $this->formattedLines = [];
        $this->inString = false;
        $this->stringDelimiter = '';
    }

    private function processToken($token): void
    {
        if (is_array($token)) {
            $tokenType = $token[0];
            $tokenValue = $token[1];
            
            switch ($tokenType) {
                case T_OPEN_TAG:
                case T_OPEN_TAG_WITH_ECHO:
                    $this->addTokenToLine($tokenValue);
                    if (trim($tokenValue) !== '<?php') {
                        $this->addLine();
                    }
                    break;
                    
                case T_CLOSE_TAG:
                    $this->addTokenToLine($tokenValue);
                    break;
                    
                case T_WHITESPACE:
                    $this->handleWhitespace($tokenValue);
                    break;
                    
                case T_COMMENT:
                case T_DOC_COMMENT:
                    $this->handleComment($tokenValue);
                    break;
                    
                case T_CONSTANT_ENCAPSED_STRING:
                case T_ENCAPSED_AND_WHITESPACE:
                    $this->handleString($tokenValue);
                    break;
                    
                default:
                    $this->addTokenToLine($tokenValue);
                    break;
            }
        } else {
            // Token simple (caractère)
            $this->handleSimpleToken($token);
        }
    }

    private function handleWhitespace(string $whitespace): void
    {
        // Compter les nouvelles lignes
        $newlineCount = substr_count($whitespace, "\n");
        
        if ($newlineCount > 0) {
            // Ajouter la ligne courante seulement si elle n'est pas vide
            if (!empty(trim($this->currentLine))) {
                $this->addLine();
            }
            
            // Limiter le nombre de lignes vides consécutives à 1
            if ($newlineCount > 1 && !empty($this->formattedLines)) {
                $this->formattedLines[] = '';
            }
        } else {
            // Ajouter un espace simple si on n'est pas au début de ligne et si le dernier caractère n'est pas déjà un espace
            if (!empty(trim($this->currentLine)) && !str_ends_with($this->currentLine, ' ')) {
                $this->addTokenToLine(' ');
            }
        }
    }

    private function handleComment(string $comment): void
    {
        $lines = explode("\n", $comment);
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $this->addLine();
            }
            $this->addTokenToLine($line);
        }
    }

    private function handleString(string $string): void
    {
        // Les chaînes peuvent être longues, on les traite spécialement
        if (strlen($this->currentLine . $string) > self::MAX_LINE_LENGTH) {
            // Si c'est une concaténation, essayer de casser au niveau du point
            if (strpos($string, '.') !== false && strpos($this->currentLine, '.') !== false) {
                $this->addLine();
                $this->addTokenToLine($string);
            } else {
                $this->tryToBreakString($string);
            }
        } else {
            $this->addTokenToLine($string);
        }
    }

    private function handleSimpleToken(string $token): void
    {
        switch ($token) {
            case '{':
                $this->addTokenToLine(' ' . $token);
                $this->addLine();
                $this->indentLevel++;
                break;
                
            case '}':
                $this->indentLevel = max(0, $this->indentLevel - 1);
                if (!empty(trim($this->currentLine))) {
                    $this->addLine();
                }
                $this->addTokenToLine($token);
                $this->addLine();
                break;
                
            case ';':
                $this->addTokenToLine($token);
                // Ajouter une nouvelle ligne seulement pour les instructions complètes
                $this->addLine();
                break;
                
            case ',':
                $this->addTokenToLine($token);
                // Si c'est dans une signature de fonction et que la ligne est longue, casser
                if (strlen($this->currentLine) > self::MAX_LINE_LENGTH - 15 || 
                    (strlen($this->currentLine) > 60 && strpos($this->currentLine, 'function') !== false)) {
                    $this->addLine();
                } else {
                    $this->addTokenToLine(' ');
                }
                break;
                
            case '(':
            case ')':
            case '[':
            case ']':
                $this->addTokenToLine($token);
                break;
                
            default:
                $this->addTokenToLine($token);
                break;
        }
    }

    private function addTokenToLine(string $token): void
    {
        $potentialLine = $this->currentLine . $token;
        
        // Si la ligne deviendrait trop longue ET qu'on peut la casser intelligemment
        if (strlen($potentialLine) > self::MAX_LINE_LENGTH && !empty(trim($this->currentLine))) {
            // Chercher un bon endroit pour casser la ligne
            $breakPoints = [' && ', ' || ', ' . ', ' => ', ', ', ' + ', ' - ', ' * ', ' / ', ' == ', ' != ', ' === ', ' !== ', ' = '];
            $bestBreakPoint = null;
            $bestBreakPos = -1;
            
            foreach ($breakPoints as $breakPoint) {
                $pos = strrpos($this->currentLine, $breakPoint);
                if ($pos !== false && $pos > $bestBreakPos) {
                    $bestBreakPos = $pos + strlen($breakPoint);
                    $bestBreakPoint = $breakPoint;
                }
            }
            
            if ($bestBreakPoint !== null) {
                // Casser à l'endroit trouvé
                $beforeBreak = substr($this->currentLine, 0, $bestBreakPos);
                $afterBreak = substr($this->currentLine, $bestBreakPos);
                
                $this->formattedLines[] = $this->getIndent() . ltrim($beforeBreak);
                $this->currentLine = str_repeat(' ', self::INDENT_SIZE) . ltrim($afterBreak . $token);
            } else {
                // Casser simplement si aucun point de cassure n'est trouvé
                $this->addLine();
                $this->currentLine = str_repeat(' ', self::INDENT_SIZE) . ltrim($token);
            }
        } else {
            $this->currentLine .= $token;
        }
        
        // Vérifier si même après ajout, la ligne est encore trop longue
        if (strlen($this->currentLine) > self::MAX_LINE_LENGTH) {
            $this->forceLineBreak();
        }
    }
    
    private function forceLineBreak(): void
    {
        $line = $this->currentLine;
        $maxLen = self::MAX_LINE_LENGTH - strlen($this->getIndent());
        
        if (strlen($line) <= $maxLen) {
            return;
        }
        
        // Chercher le meilleur endroit pour casser dans les derniers caractères
        $breakPoints = [', ', ' = ', ' '];
        $bestPos = false;
        
        for ($i = $maxLen; $i >= $maxLen - 20 && $i >= 0; $i--) {
            foreach ($breakPoints as $breakPoint) {
                if (substr($line, $i, strlen($breakPoint)) === $breakPoint) {
                    $bestPos = $i + strlen($breakPoint);
                    break 2;
                }
            }
        }
        
        if ($bestPos !== false) {
            $beforeBreak = substr($line, 0, $bestPos);
            $afterBreak = substr($line, $bestPos);
            
            $this->currentLine = trim($beforeBreak);
            $this->addLine();
            $this->currentLine = str_repeat(' ', self::INDENT_SIZE) . ltrim($afterBreak);
        }
    }

    private function tryToBreakString(string $string): void
    {
        // Pour les chaînes très longues, on essaie de les couper intelligemment
        if (strpos($string, ' ') !== false) {
            $words = explode(' ', $string);
            foreach ($words as $index => $word) {
                if ($index > 0) {
                    $this->addTokenToLine(' ');
                }
                
                if (strlen($this->currentLine . $word) > self::MAX_LINE_LENGTH) {
                    $this->addLine();
                }
                $this->addTokenToLine($word);
            }
        } else {
            $this->addTokenToLine($string);
        }
    }

    private function addLine(): void
    {
        $trimmedLine = trim($this->currentLine);
        
        if (empty($trimmedLine)) {
            // Éviter d'ajouter trop de lignes vides consécutives
            if (!empty($this->formattedLines) && trim(end($this->formattedLines)) !== '') {
                $this->formattedLines[] = '';
            }
        } else {
            // Ajouter l'indentation appropriée si la ligne n'est pas vide
            $this->formattedLines[] = $this->getIndent() . ltrim($this->currentLine);
        }
        $this->currentLine = '';
    }

    private function getIndent(): string
    {
        return str_repeat(' ', $this->indentLevel * self::INDENT_SIZE);
    }

    public function formatFileInPlace(string $filePath, bool $backup = true): bool
    {
        try {
            if ($backup) {
                $backupPath = $filePath . '.backup.' . date('Y-m-d-H-i-s');
                if (!copy($filePath, $backupPath)) {
                    throw new RuntimeException("Impossible de créer le backup '$backupPath'.");
                }
                echo "💾 Backup créé : $backupPath\n";
            }

            $formattedContent = $this->formatFile($filePath);
            
            if (file_put_contents($filePath, $formattedContent) === false) {
                throw new RuntimeException("Impossible d'écrire dans le fichier '$filePath'.");
            }

            return true;
        } catch (Exception $e) {
            echo "❌ Erreur lors du formatage de '$filePath' : " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Script principal
function main(): void
{
    $options = getopt('f:o:h', ['file:', 'output:', 'help', 'in-place', 'no-backup']);
    
    if (isset($options['h']) || isset($options['help'])) {
        showHelp();
        return;
    }

    $filePath = $options['f'] ?? $options['file'] ?? null;
    
    if (!$filePath) {
        echo "❌ Erreur : Vous devez spécifier un fichier avec -f ou --file\n\n";
        showHelp();
        exit(1);
    }

    if (!file_exists($filePath)) {
        echo "❌ Erreur : Le fichier '$filePath' n'existe pas.\n";
        exit(1);
    }

    echo "🔧 Formatage du fichier : $filePath\n";
    echo "📏 Limite de longueur : 80 caractères\n\n";

    $formatter = new PHPFileFormatter();
    
    try {
        if (isset($options['in-place'])) {
            // Formatage en place
            $backup = !isset($options['no-backup']);
            $success = $formatter->formatFileInPlace($filePath, $backup);
            
            if ($success) {
                echo "✅ Fichier formaté avec succès !\n";
            } else {
                exit(1);
            }
        } else {
            // Formatage vers un nouveau fichier ou stdout
            $formattedContent = $formatter->formatFile($filePath);
            
            $outputPath = $options['o'] ?? $options['output'] ?? null;
            
            if ($outputPath) {
                if (file_put_contents($outputPath, $formattedContent) === false) {
                    echo "❌ Erreur : Impossible d'écrire dans '$outputPath'.\n";
                    exit(1);
                }
                echo "✅ Fichier formaté sauvegardé dans : $outputPath\n";
            } else {
                echo $formattedContent;
            }
        }
    } catch (Exception $e) {
        echo "❌ Erreur : " . $e->getMessage() . "\n";
        exit(1);
    }
}

function showHelp(): void
{
    echo "Usage: php bin/format-php.php [OPTIONS]\n\n";
    echo "Options :\n";
    echo "  -f, --file <file>     Fichier PHP à formater (obligatoire)\n";
    echo "  -o, --output <file>   Fichier de sortie (défaut: affichage sur stdout)\n";
    echo "  --in-place           Modifie le fichier directement\n";
    echo "  --no-backup          Ne crée pas de backup (avec --in-place)\n";
    echo "  -h, --help           Affiche cette aide\n\n";
    echo "Exemples :\n";
    echo "  php bin/format-php.php -f src/Controller/AuthController.php\n";
    echo "  php bin/format-php.php -f src/Entity/User.php -o src/Entity/User.formatted.php\n";
    echo "  php bin/format-php.php -f src/Service/UserService.php --in-place\n";
    echo "  php bin/format-php.php -f src/Repository/UserRepository.php --in-place --no-backup\n";
}

main();
