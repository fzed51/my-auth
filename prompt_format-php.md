# Spécifications pour le script format-php.php

## Contexte initial
- Création d'un script PHP qui scanne les fichiers PHP et détecte ceux qui comportent des lignes de plus de 80 caractères
- Création d'un 2e script qui prend en paramètre un fichier PHP, utilise les tokens pour découper le fichier et le reconstruit token par token en faisant en sorte de ne pas faire de ligne de plus de 80 caractères

## Évolution des exigences

### Première version
- Script simple qui reconstruit le fichier en limitant les lignes à 80 caractères
- Utilisation des tokens PHP pour parser le code
- Gestion basique de la longueur des lignes

### Version finale demandée
**"PHPFileFormatter doit reconstruire le fichier en appliquant les règles de PSR-12 en ignorant les sauts de ligne et les espaces du fichier d'origine."**

### Évolution vers l'approche AST
**Décision technique** : Passer d'une approche basée sur les tokens à une approche basée sur l'AST (Abstract Syntax Tree) pour une meilleure compréhension sémantique du code et un formatage plus intelligent.

## Approche recommandée : AST vs Tokens

### Pourquoi choisir l'AST plutôt que les tokens
1. **Compréhension sémantique** : L'AST comprend la structure logique du code (classes, méthodes, conditions)
2. **Formatage contextuel** : Règles différentes selon le type de structure
3. **Robustesse** : Moins d'erreurs de parsing et de reconstruction
4. **Maintien des commentaires** : Association correcte avec les structures
5. **Gestion intelligente des lignes longues** : Cassure contextuelle selon la structure

### Comparaison des approches

#### Tokens (approche initiale)
```php
// Séquence linéaire : T_PUBLIC, T_FUNCTION, T_STRING, '(', T_STRING, T_VARIABLE, ')', '{'
// Difficile de savoir le contexte pour formater intelligemment
public function __construct(string $message) {
```

#### AST (approche recommandée)
```php
// Structure hiérarchique :
// ClassMethod -> Parameters -> Parameter -> TypeHint
// Formatage intelligent possible selon le contexte
public function longMethodName(
    string $parameterOne,
    array $parameterTwo,
    ?callable $callback = null
): ResponseInterface {
```

### Implementation avec nikic/php-parser

#### Dépendance requise
```bash
composer require nikic/php-parser
```

#### Structure du code avec AST
```php
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

class PSR12ASTFormatter
{
    private PrettyPrinter\Standard $printer;
    private int $maxLineLength = 80;
    
    public function format(string $code): string
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($code);
        
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new PSR12NodeVisitor());
        
        $ast = $traverser->traverse($ast);
        
        return $this->printer->prettyPrint($ast);
    }
}

class PSR12NodeVisitor extends NodeVisitor
{
    public function enterNode(Node $node)
    {
        // Formatage contextuel selon le type de nœud
        if ($node instanceof Node\Stmt\Class_) {
            // Règles spécifiques aux classes
        } elseif ($node instanceof Node\Stmt\ClassMethod) {
            // Règles spécifiques aux méthodes
        } elseif ($node instanceof Node\Stmt\If_) {
            // Règles spécifiques aux structures de contrôle
        }
    }
}
```

### Avantages spécifiques pour PSR-12
- **Classes** : Accolades sur nouvelle ligne automatiquement gérées
- **Méthodes** : Gestion intelligente des paramètres longs avec cassure appropriée
- **Structures de contrôle** : Espacement et placement des accolades corrects
- **Imports** : Tri et organisation automatique des `use` statements
- **Commentaires DocBlock** : Préservation et formatage selon le contexte
- **Indentation** : Calcul automatique selon la profondeur dans l'AST

## Spécifications techniques détaillées

### Fonctionnalités principales
1. **Parser avec tokens** : Utiliser `token_get_all()` pour analyser le code PHP
2. **Reconstruction complète** : Ignorer totalement la mise en forme originale (espaces, sauts de ligne)
3. **Conformité PSR-12** : Appliquer toutes les règles du standard PSR-12
4. **Limite de 80 caractères** : Maintenir cette contrainte pour la longueur des lignes
5. **Système de sauvegarde** : Créer une copie de sauvegarde avant modification

### Règles PSR-12 à implémenter
1. **Indentation** : 4 espaces (pas de tabs)
2. **Accolades** : 
   - Classes et méthodes : accolade ouvrante sur nouvelle ligne
   - Structures de contrôle : accolade ouvrante sur même ligne
3. **Espacement** :
   - Espace après les mots-clés de contrôle (`if`, `for`, `while`, etc.)
   - Espace autour des opérateurs (`=`, `+`, `-`, etc.)
   - Pas d'espace avant les points-virgules
   - Espace après les virgules dans les listes de paramètres
4. **Déclarations de fonctions** :
   - Visibilité + `function` + nom + parenthèses
   - Paramètres avec espaces appropriés
   - Type hints correctement espacés
5. **Déclarations de classes** :
   - `class` + nom + accolade sur nouvelle ligne
   - Propriétés et méthodes correctement indentées

### Gestion des tokens spéciaux
- **T_WHITESPACE** : Ignorer complètement, reconstruire selon PSR-12
- **T_COMMENT** et **T_DOC_COMMENT** : Préserver mais reformater l'indentation
- **T_OPEN_TAG** et **T_CLOSE_TAG** : Gérer correctement
- **Chaînes de caractères** : Préserver le contenu exact
- **Opérateurs** : Espacer selon les règles PSR-12

### Structure du code
```php
class PHPFileFormatter
{
    private array $tokens;
    private int $currentIndex;
    private string $output;
    private int $indentLevel;
    private int $currentLineLength;
    
    public function format(string $code): string
    public function processCurrentToken(): void
    private function handleSimpleToken(array $token): void
    // ... autres méthodes
}
```

### Méthodes utilitaires requises
- `getNextNonWhitespaceToken()` : Pour analyser le contexte
- `addIndentation()` : Gérer l'indentation courante
- `addNewlineIfNeeded()` : Contrôler les sauts de ligne
- `canFitOnCurrentLine()` : Vérifier la limite de 80 caractères

### Cas d'usage spécifiques
1. **Déclarations de fonctions** : `public function __construct(string $message)`
2. **Structures de contrôle** : `if ($condition) {`
3. **Déclarations de classes** : `class ExceptionName extends Exception`
4. **Opérateurs d'assignation** : `$this->message = $message;`
5. **Appels de méthodes** : `parent::__construct($message);`

### Contraintes techniques
- **PHP 8.0+** compatible
- **Dépendance nikic/php-parser** : Pour l'analyse AST
- **Gestion d'erreurs** robuste
- **Messages informatifs** pour l'utilisateur
- **Mode dry-run** pour prévisualiser les changements

### Interface utilisateur
```bash
php bin/format-php.php -f fichier.php  # Formater un fichier
php bin/format-php.php -h             # Aide
```

### Intégration avec Composer
```json
"scripts": {
    "format-php": "php bin/format-php.php"
}
```

## Problèmes rencontrés et solutions

### Espacement entre tokens
**Problème** : Sortie comme "publicfunction" au lieu de "public function"
**Solution avec tokens** : Implémenter une logique d'espacement contextuelle entre les différents types de tokens
**Solution avec AST** : Le PrettyPrinter gère automatiquement l'espacement selon le contexte sémantique

### Reconstruction vs préservation
**Décision** : Reconstruction complète en ignorant la mise en forme originale
**Justification** : Assurer une conformité PSR-12 stricte

### Gestion de la longueur des lignes
**Approche** : Casser les lignes longues de manière intelligente selon le contexte (paramètres de fonction, chaînage de méthodes, etc.)

## Points d'attention
1. **Préservation du comportement** : Le code formaté doit être fonctionnellement identique
2. **Lisibilité** : Le résultat doit être plus lisible que l'original
3. **Cohérence** : Application uniforme des règles PSR-12
4. **Performance** : Traitement efficace même pour de gros fichiers

## Tests et validation
- Validation syntaxique avec `php -l`
- Tests sur différents types de fichiers PHP
- Vérification de la conformité PSR-12
- Tests de régression pour s'assurer que le comportement du code n'est pas modifié
