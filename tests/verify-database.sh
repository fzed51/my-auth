#!/bin/bash

# Script de vérification de la base de données
echo "=== VERIFICATION DE LA BASE DE DONNEES ==="
echo "Date: $(date)"
echo ""

echo "=== TABLES EXISTANTES ==="
docker-compose exec mysql mysql -u auth_user -p'auth_password' my_auth -e "SHOW TABLES;"

echo ""
echo "=== STRUCTURE DE LA TABLE USERS ==="
docker-compose exec mysql mysql -u auth_user -p'auth_password' my_auth -e "DESCRIBE users;"

echo ""
echo "=== STRUCTURE DE LA TABLE EMAIL_VERIFICATIONS ==="
docker-compose exec mysql mysql -u auth_user -p'auth_password' my_auth -e "DESCRIBE email_verifications;"

echo ""
echo "=== STRUCTURE DE LA TABLE JWT_BLACKLIST ==="
docker-compose exec mysql mysql -u auth_user -p'auth_password' my_auth -e "DESCRIBE jwt_blacklist;"

echo ""
echo "=== CONTRAINTES DE CLES ETRANGERES ==="
docker-compose exec mysql mysql -u auth_user -p'auth_password' my_auth -e "
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'my_auth' 
    AND REFERENCED_TABLE_NAME IS NOT NULL;"

echo ""
echo "=== TEST TERMINE ==="
