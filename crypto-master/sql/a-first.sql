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
-- 2. VUES
-- --------------------------------------------------------

-- Vue: liste_achat
CREATE OR REPLACE VIEW liste_achat AS
SELECT 
    p.id_utilisateur,
    u.nom as nom_utilisateur,
    c.nom_crypto,
    pf.nbr_crypto,
    pf.prix_unitaire,
    pf.prix_total_avec_commission,
    pf.date_action
FROM portefeuille_fille pf
JOIN portefeuille p ON pf.id_portefeuille = p.id
JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
JOIN crypto c ON pf.id_crypto = c.id_crypto
WHERE pf.type_action = 'achat'
ORDER BY pf.date_action DESC;

-- Vue: vue_positions_achat
CREATE OR REPLACE VIEW vue_positions_achat AS
WITH positions AS (
    SELECT 
        p.id_utilisateur,
        u.nom as nom_utilisateur,
        c.nom_crypto,
        SUM(CASE WHEN pf.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END) -
        COALESCE(SUM(CASE WHEN ht.type_action = 'vente' THEN ht.nbrcrypto ELSE 0 END), 0) as quantite_restante,
        CASE 
            WHEN SUM(CASE WHEN pf.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END) > 0 
            THEN SUM(CASE WHEN pf.type_action = 'achat' THEN pf.prix_total_avec_commission ELSE 0 END) / 
                 SUM(CASE WHEN pf.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END)
            ELSE 0 
        END as prix_moyen_achat
    FROM portefeuille p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
    JOIN crypto c ON pf.id_crypto = c.id_crypto
    LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille AND pf.id_crypto = ht.id_crypto
    GROUP BY p.id_utilisateur, u.nom, c.nom_crypto
)
SELECT 
    p.*,
    vc.prixevoluer as prix_actuel,
    ((vc.prixevoluer - p.prix_moyen_achat) / p.prix_moyen_achat * 100) as rendement_pourcentage
FROM positions p
JOIN crypto c ON p.nom_crypto = c.nom_crypto
JOIN LATERAL (
    SELECT prixevoluer 
    FROM variationcrypto v 
    WHERE v.id_crypto = c.id_crypto 
    ORDER BY date_variation DESC 
    LIMIT 1
) vc ON true
WHERE p.quantite_restante > 0;

-- --------------------------------------------------------
-- 3. FONCTIONS
-- --------------------------------------------------------

-- Fonction: calculer_solde_crypto
CREATE OR REPLACE FUNCTION calculer_solde_crypto(
    p_id_utilisateur INTEGER,
    p_id_crypto INTEGER
) RETURNS NUMERIC AS $$
BEGIN
    RETURN (
        SELECT 
            COALESCE(SUM(CASE 
                WHEN pf.type_action = 'achat' THEN pf.nbr_crypto 
                WHEN ht.type_action = 'vente' THEN -ht.nbrcrypto
                ELSE 0 
            END), 0)
        FROM portefeuille p
        LEFT JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille AND pf.id_crypto = p_id_crypto
        LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille AND ht.id_crypto = p_id_crypto
        WHERE p.id_utilisateur = p_id_utilisateur
    );
END;
$$ LANGUAGE plpgsql;

-- Fonction: calculer_prix_moyen_achat
CREATE OR REPLACE FUNCTION calculer_prix_moyen_achat(
    p_id_utilisateur INTEGER,
    p_id_crypto INTEGER
) RETURNS NUMERIC AS $$
DECLARE
    total_crypto NUMERIC;
    total_cout NUMERIC;
BEGIN
    SELECT 
        COALESCE(SUM(nbr_crypto), 0),
        COALESCE(SUM(prix_total_avec_commission), 0)
    INTO total_crypto, total_cout
    FROM portefeuille p
    JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
    WHERE p.id_utilisateur = p_id_utilisateur 
    AND pf.id_crypto = p_id_crypto
    AND pf.type_action = 'achat';

    IF total_crypto = 0 THEN
        RETURN 0;
    END IF;

    RETURN total_cout / total_crypto;
END;
$$ LANGUAGE plpgsql;

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