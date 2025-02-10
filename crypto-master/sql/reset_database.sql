-- Désactiver temporairement les contraintes de clés étrangères
SET session_replication_role = 'replica';

-- Vider les tables dans l'ordre approprié
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

-- Réinitialiser les séquences
ALTER SEQUENCE IF EXISTS utilisateur_id_utilisateur_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS crypto_id_crypto_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS portefeuille_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS portefeuille_fille_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS historique_transactions_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS historique_utilisateur_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS action_portefeuille_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS commission_id_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS variationcrypto_id_seq RESTART WITH 1;

-- Insérer les données de base nécessaires pour le fonctionnement de l'application
INSERT INTO crypto (nom_crypto, symbole, prix_actuel) VALUES
('Bitcoin', 'BTC', 40000),
('Ethereum', 'ETH', 2500),
('Binance Coin', 'BNB', 300);

-- Réactiver les contraintes de clés étrangères
SET session_replication_role = 'origin';

-- Vérifier que toutes les tables sont vides (sauf crypto qui contient les données de base)
SELECT 
    table_name, 
    (xpath('/row/cnt/text()', xml_count))[1]::text::int as row_count
FROM (
    SELECT 
        table_name, 
        query_to_xml(format('SELECT COUNT(*) as cnt FROM %I', table_name), false, true, '') as xml_count
    FROM information_schema.tables
    WHERE table_schema = 'public' 
    AND table_type = 'BASE TABLE'
) subq
ORDER BY table_name;
