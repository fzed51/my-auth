# Scripts de Formatage PHP

Ce répertoire contient des scripts pour détecter et reformater automatiquement les fichiers PHP avec des lignes dépassant 80 caractères.

## Scripts disponibles

### 1. `scan-long-lines.php`
Scanne les fichiers PHP et détecte ceux avec des lignes de plus de 80 caractères.

**Usage :**
```bash
# Via Composer (recommandé)
composer scan-long-lines

# Directement
php bin/scan-long-lines.php

# Avec options
php bin/scan-long-lines.php -d src/
php bin/scan-long-lines.php --files-only
```

**Options :**
- `-d, --directory <dir>` : Répertoire à scanner (défaut: répertoire courant)
- `--files-only` : Affiche seulement la liste des fichiers (sans détails)
- `-h, --help` : Affiche l'aide

### 2. `format-php.php`
Reformate un fichier PHP en utilisant les tokens pour respecter la limite de 80 caractères.

**Usage :**
```bash
# Afficher le résultat sur stdout
php bin/format-php.php -f src/Controller/AuthController.php

# Sauvegarder dans un nouveau fichier
php bin/format-php.php -f src/Entity/User.php -o src/Entity/User.formatted.php

# Modifier le fichier en place (avec backup)
php bin/format-php.php -f src/Service/UserService.php --in-place

# Modifier sans backup
php bin/format-php.php -f src/Repository/UserRepository.php --in-place --no-backup
```

**Options :**
- `-f, --file <file>` : Fichier PHP à formater (obligatoire)
- `-o, --output <file>` : Fichier de sortie (défaut: stdout)
- `--in-place` : Modifie le fichier directement
- `--no-backup` : Ne crée pas de backup (avec --in-place)
- `-h, --help` : Affiche l'aide

### 3. `auto-format.php`
Script automatique qui combine les deux précédents pour reformater tous les fichiers problématiques.

**Usage :**
```bash
# Via Composer (recommandé)
composer auto-format

# Directement
php bin/auto-format.php

# Mode test (sans modification)
php bin/auto-format.php --dry-run

# Formater un répertoire spécifique
php bin/auto-format.php -d src/

# Sans backup
php bin/auto-format.php --no-backup
```

**Options :**
- `-d, --directory <dir>` : Répertoire à traiter (défaut: répertoire courant)
- `--dry-run` : Mode test - affiche les fichiers à traiter sans les modifier
- `--no-backup` : Ne crée pas de backup des fichiers originaux
- `-h, --help` : Affiche l'aide

## Workflow recommandé

### Option 1 : Processus automatique
```bash
# 1. Voir quels fichiers seraient modifiés
composer auto-format -- --dry-run

# 2. Reformater automatiquement tous les fichiers
composer auto-format

# 3. Vérifier les modifications
git diff
```

### Option 2 : Processus manuel
```bash
# 1. Scanner les fichiers problématiques
composer scan-long-lines

# 2. Reformater un fichier spécifique
php bin/format-php.php -f src/Controller/AuthController.php --in-place

# 3. Répéter pour chaque fichier ou utiliser une boucle
for file in $(composer scan-long-lines -- --files-only); do
    php bin/format-php.php -f "$file" --in-place
done
```

## Fonctionnalités

### Scanner (`scan-long-lines.php`)
- ✅ Scan récursif des fichiers PHP
- ✅ Exclusion automatique des dossiers vendor/, var/, cache/, etc.
- ✅ Détection des lignes > 80 caractères
- ✅ Affichage détaillé avec numéros de ligne
- ✅ Mode liste simple pour scripting
- ✅ Résumé statistique

### Formateur (`format-php.php`)
- ✅ Analyse par tokens PHP natifs
- ✅ Respect de la syntaxe PHP
- ✅ Gestion intelligente de l'indentation
- ✅ Traitement spécial des commentaires et chaînes
- ✅ Backup automatique optionnel
- ✅ Modes de sortie multiples (stdout, fichier, en place)

### Automatique (`auto-format.php`)
- ✅ Combinaison des deux scripts
- ✅ Mode dry-run pour test
- ✅ Traitement par lot
- ✅ Rapport de progression
- ✅ Résumé des opérations

## Exemples d'utilisation

### Intégration dans le workflow de développement
```bash
# Avant un commit
composer scan-long-lines
composer auto-format -- --dry-run
composer auto-format

# Dans un hook pre-commit
#!/bin/sh
if composer scan-long-lines -- --files-only | grep -q .; then
    echo "❌ Des fichiers ont des lignes trop longues. Utilisation du formateur automatique..."
    composer auto-format
    echo "✅ Fichiers reformatés. Veuillez vérifier et commiter à nouveau."
    exit 1
fi
```

### CI/CD
```bash
# Vérification dans la CI
composer scan-long-lines
if [ $? -eq 0 ]; then
    echo "✅ Tous les fichiers respectent la limite de 80 caractères"
else
    echo "❌ Des fichiers dépassent la limite de 80 caractères"
    exit 1
fi
```

## Configuration

Les scripts utilisent une limite fixe de **80 caractères** par ligne avec une indentation de **4 espaces**.

Pour modifier ces valeurs, éditez les constantes dans les fichiers :
- `MAX_LINE_LENGTH = 80`
- `INDENT_SIZE = 4`

## Limitations

- Les chaînes très longues sans espaces peuvent rester longues
- Les commentaires sur une ligne peuvent être difficiles à couper proprement
- Le formateur privilégie la lisibilité à la compacité parfaite
- Certaines constructions PHP complexes peuvent nécessiter un ajustement manuel

## Dépendances

- PHP 8.0+
- Extension `tokenizer` (généralement incluse par défaut)
- Extension `mbstring` pour le support Unicode
