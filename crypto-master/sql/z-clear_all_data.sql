-- Désactiver temporairement les contraintes de clés étrangères
SET session_replication_role = 'replica';

-- Vider toutes les tables
TRUNCATE TABLE messenger_messages CASCADE;
TRUNCATE TABLE historique_transactions CASCADE;
TRUNCATE TABLE historique_utilisateur CASCADE;
TRUNCATE TABLE action_portefeuille CASCADE;
TRUNCATE TABLE commission CASCADE;
TRUNCATE TABLE variationcrypto CASCADE;
TRUNCATE TABLE portefeuille_fille CASCADE;
TRUNCATE TABLE portefeuille CASCADE;
TRUNCATE TABLE crypto CASCADE;
TRUNCATE TABLE utilisateur CASCADE;
TRUNCATE TABLE "user" CASCADE;
TRUNCATE TABLE doctrine_migration_versions CASCADE;

-- Réinitialiser toutes les séquences
ALTER SEQUENCE IF EXISTS utilisateur_id_utilisateur_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS crypto_id_crypto_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS portefeuille_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS portefeuille_fille_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS historique_transactions_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS historique_utilisateur_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS action_portefeuille_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS commission_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS variationcrypto_id_seq RESTART WITH 1;

-- Réactiver les contraintes de clés étrangères
SET session_replication_role = 'origin';
