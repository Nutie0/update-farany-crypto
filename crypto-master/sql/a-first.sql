-- ========================================================
-- Structure complète de la base de données Crypto
-- ========================================================

-- Suppression des tables existantes si nécessaire
DROP TABLE IF EXISTS historique_utilisateur CASCADE;
DROP TABLE IF EXISTS variationcrypto CASCADE;
DROP TABLE IF EXISTS historique_transactions CASCADE;
DROP TABLE IF EXISTS portefeuille_fille CASCADE;
DROP TABLE IF EXISTS action_portefeuille CASCADE;
DROP TABLE IF EXISTS portefeuille CASCADE;
DROP TABLE IF EXISTS crypto CASCADE;
DROP TABLE IF EXISTS utilisateur CASCADE;

-- --------------------------------------------------------
-- 1. TABLES PRINCIPALES
-- --------------------------------------------------------

-- Table: utilisateur
CREATE TABLE utilisateur (
    id_utilisateur SERIAL PRIMARY KEY,
    email VARCHAR(180) NOT NULL UNIQUE,
    roles JSON NOT NULL,
    token TEXT,
    nom VARCHAR(255)
);

-- Table: crypto
CREATE TABLE crypto (
    id_crypto SERIAL PRIMARY KEY,
    nom_crypto VARCHAR(50) NOT NULL UNIQUE,
    symbole VARCHAR(10) NOT NULL UNIQUE
);

-- Table: portefeuille
CREATE TABLE portefeuille (
    id SERIAL PRIMARY KEY,
    id_utilisateur INTEGER NOT NULL UNIQUE,
    solde_utilisateur NUMERIC(15,2) NOT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);

-- Table: portefeuille_fille
CREATE TABLE portefeuille_fille (
    id SERIAL PRIMARY KEY,
    id_portefeuille INTEGER NOT NULL,
    id_crypto INTEGER NOT NULL,
    nbr_crypto NUMERIC(15,8) NOT NULL,
    prix_unitaire NUMERIC(15,2) NOT NULL,
    prix_total NUMERIC(15,2) NOT NULL,
    taux_commission NUMERIC(4,2) NOT NULL,
    montant_commission NUMERIC(15,2) NOT NULL,
    prix_total_avec_commission NUMERIC(15,2) NOT NULL,
    type_action VARCHAR(10) NOT NULL,
    date_action TIMESTAMP NOT NULL,
    FOREIGN KEY (id_portefeuille) REFERENCES portefeuille(id),
    FOREIGN KEY (id_crypto) REFERENCES crypto(id_crypto)
);

-- Table: historique_transactions
CREATE TABLE historique_transactions (
    id SERIAL PRIMARY KEY,
    id_portefeuille INTEGER NOT NULL,
    id_crypto INTEGER NOT NULL,
    nbrcrypto NUMERIC(15,8) NOT NULL,
    prix_unitaire NUMERIC(15,2) NOT NULL,
    prix_total NUMERIC(15,2) NOT NULL,
    taux_commission NUMERIC(4,2) NOT NULL,
    montant_commission NUMERIC(15,2) NOT NULL,
    prix_total_avec_commission NUMERIC(15,2) NOT NULL,
    type_action VARCHAR(10) NOT NULL,
    date_action TIMESTAMP NOT NULL,
    FOREIGN KEY (id_portefeuille) REFERENCES portefeuille(id),
    FOREIGN KEY (id_crypto) REFERENCES crypto(id_crypto)
);

-- Table: variationcrypto
CREATE TABLE variationcrypto (
    id_variation SERIAL PRIMARY KEY,
    id_crypto INTEGER NOT NULL,
    prixevoluer NUMERIC(15,2) NOT NULL,
    date_variation TIMESTAMP NOT NULL,
    FOREIGN KEY (id_crypto) REFERENCES crypto(id_crypto)
);

-- Table: action_portefeuille
CREATE TABLE action_portefeuille (
    id_action SERIAL PRIMARY KEY,
    type_action VARCHAR(10) NOT NULL UNIQUE
);

-- Table: historique_utilisateur
CREATE TABLE historique_utilisateur (
    id_historique_utilisateur SERIAL PRIMARY KEY,
    id INTEGER NOT NULL,
    montant NUMERIC(15,2) NOT NULL,
    type_action VARCHAR(10) NOT NULL,
    date_action TIMESTAMP NOT NULL,
    solde_avant NUMERIC(15,2) NOT NULL,
    solde_apres NUMERIC(15,2) NOT NULL,
    FOREIGN KEY (id) REFERENCES portefeuille(id)
);


-- --------------------------------------------------------
-- 4. INDEX
-- --------------------------------------------------------

-- Index sur les dates pour optimiser les recherches temporelles
CREATE INDEX IF NOT EXISTS idx_portefeuille_fille_date ON portefeuille_fille(date_action);
CREATE INDEX IF NOT EXISTS idx_historique_transactions_date ON historique_transactions(date_action);
CREATE INDEX IF NOT EXISTS idx_variationcrypto_date ON variationcrypto(date_variation);
CREATE INDEX IF NOT EXISTS idx_historique_utilisateur_date ON historique_utilisateur(date_action);

-- Index sur les clés étrangères pour optimiser les jointures
CREATE INDEX IF NOT EXISTS idx_portefeuille_fille_portefeuille ON portefeuille_fille(id_portefeuille);
CREATE INDEX IF NOT EXISTS idx_portefeuille_fille_crypto ON portefeuille_fille(id_crypto);
CREATE INDEX IF NOT EXISTS idx_historique_transactions_portefeuille ON historique_transactions(id_portefeuille);
CREATE INDEX IF NOT EXISTS idx_historique_transactions_crypto ON historique_transactions(id_crypto);
CREATE INDEX IF NOT EXISTS idx_variationcrypto_crypto ON variationcrypto(id_crypto);

-- --------------------------------------------------------
-- 5. DONNÉES INITIALES
-- --------------------------------------------------------

-- Insertion des types d'actions de base
INSERT INTO action_portefeuille (type_action) VALUES
    ('achat'),
    ('vente'),
    ('depot'),
    ('retrait')
ON CONFLICT (type_action) DO NOTHING;