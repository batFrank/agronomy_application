-- La base de datos symfony_base ya se crea via POSTGRES_DB en docker-compose
-- Solo creamos la base de datos de test
SELECT 'CREATE DATABASE symfony_base_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'symfony_base_test')\gexec
