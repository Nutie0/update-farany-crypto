-- ========================================================
-- Documentation complète de la base de données Crypto
-- ========================================================

-- --------------------------------------------------------
-- 1. TABLES PRINCIPALES
-- --------------------------------------------------------

-- Table: utilisateur
-- Description: Stocke les informations des utilisateurs
CREATE TABLE IF NOT EXISTS utilisateur (
    id_utilisateur SERIAL PRIMARY KEY,
    email VARCHAR(180) NOT NULL UNIQUE,
    roles JSON NOT NULL,
    token TEXT,
    nom VARCHAR(255)
);
COMMENT ON TABLE utilisateur IS 'Table principale des utilisateurs avec leurs informations de connexion';

-- Table: portefeuille
-- Description: Portefeuille principal de l'utilisateur
CREATE TABLE IF NOT EXISTS portefeuille (
    id SERIAL PRIMARY KEY,
    id_utilisateur INTEGER NOT NULL UNIQUE,
    solde_utilisateur NUMERIC(15,2) NOT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);
COMMENT ON TABLE portefeuille IS 'Portefeuille principal contenant le solde en euros';

-- Table: portefeuille_fille
-- Description: Détails des transactions d'achat de cryptomonnaies
CREATE TABLE IF NOT EXISTS portefeuille_fille (
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
COMMENT ON TABLE portefeuille_fille IS 'Détails des achats de cryptomonnaies';

-- Table: historique_transactions
-- Description: Historique des ventes de cryptomonnaies
CREATE TABLE IF NOT EXISTS historique_transactions (
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
COMMENT ON TABLE historique_transactions IS 'Historique des ventes de cryptomonnaies';

-- Table: crypto
-- Description: Liste des cryptomonnaies disponibles
CREATE TABLE IF NOT EXISTS crypto (
    id_crypto SERIAL PRIMARY KEY,
    nom_crypto VARCHAR(50) NOT NULL UNIQUE,
    symbole VARCHAR(10) NOT NULL UNIQUE
);
COMMENT ON TABLE crypto IS 'Catalogue des cryptomonnaies disponibles';

-- Table: variationcrypto
-- Description: Historique des prix des cryptomonnaies
CREATE TABLE IF NOT EXISTS variationcrypto (
    id_variation SERIAL PRIMARY KEY,
    id_crypto INTEGER NOT NULL,
    prixevoluer NUMERIC(15,2) NOT NULL,
    date_variation TIMESTAMP NOT NULL,
    FOREIGN KEY (id_crypto) REFERENCES crypto(id_crypto)
);
COMMENT ON TABLE variationcrypto IS 'Historique des variations de prix des cryptomonnaies';

-- Table: action_portefeuille
-- Description: Types d'actions possibles sur le portefeuille
CREATE TABLE IF NOT EXISTS action_portefeuille (
    id_action SERIAL PRIMARY KEY,
    type_action VARCHAR(10) NOT NULL UNIQUE
);
COMMENT ON TABLE action_portefeuille IS 'Types d''actions possibles (achat, vente, dépôt, retrait)';

-- Table: historique_utilisateur
-- Description: Historique des mouvements du solde en euros
CREATE TABLE IF NOT EXISTS historique_utilisateur (
    id_historique_utilisateur SERIAL PRIMARY KEY,
    id INTEGER NOT NULL,
    montant NUMERIC(15,2) NOT NULL,
    type_action VARCHAR(10) NOT NULL,
    date_action TIMESTAMP NOT NULL,
    solde_avant NUMERIC(15,2) NOT NULL,
    solde_apres NUMERIC(15,2) NOT NULL,
    FOREIGN KEY (id) REFERENCES portefeuille(id)
);
COMMENT ON TABLE historique_utilisateur IS 'Historique des dépôts et retraits en euros';

-- --------------------------------------------------------
-- 2. VUES
-- --------------------------------------------------------

-- Vue: liste_achat
-- Description: Vue des achats en cours
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
-- Description: Vue des positions actuelles
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
-- 3. FONCTIONS PRINCIPALES
-- --------------------------------------------------------

-- Fonction: calculer_solde_crypto
-- Description: Calcule le solde actuel d'une crypto pour un utilisateur
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
-- Description: Calcule le prix moyen d'achat d'une crypto pour un utilisateur
CREATE OR REPLACE FUNCTION calculer_prix_moyen_achat(
    p_id_utilisateur INTEGER,
    p_id_crypto INTEGER
) RETURNS NUMERIC AS $$
DECLARE
    v_total_achats NUMERIC;
    v_total_quantite NUMERIC;
BEGIN
    SELECT 
        SUM(pf.prix_total_avec_commission),
        SUM(pf.nbr_crypto)
    INTO v_total_achats, v_total_quantite
    FROM portefeuille p
    JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
    WHERE p.id_utilisateur = p_id_utilisateur 
    AND pf.id_crypto = p_id_crypto
    AND pf.type_action = 'achat';

    IF v_total_quantite > 0 THEN
        RETURN v_total_achats / v_total_quantite;
    ELSE
        RETURN 0;
    END IF;
END;
$$ LANGUAGE plpgsql;

-- Fonction: calculer_rendement_crypto
-- Description: Calcule le rendement d'une crypto pour un utilisateur
CREATE OR REPLACE FUNCTION calculer_rendement_crypto(
    p_id_utilisateur INTEGER,
    p_id_crypto INTEGER
) RETURNS NUMERIC AS $$
DECLARE
    v_prix_moyen_achat NUMERIC;
    v_prix_actuel NUMERIC;
BEGIN
    -- Récupérer le prix moyen d'achat
    v_prix_moyen_achat := calculer_prix_moyen_achat(p_id_utilisateur, p_id_crypto);
    
    -- Récupérer le dernier prix
    SELECT prixevoluer INTO v_prix_actuel
    FROM variationcrypto
    WHERE id_crypto = p_id_crypto
    ORDER BY date_variation DESC
    LIMIT 1;

    -- Calculer le rendement
    IF v_prix_moyen_achat > 0 THEN
        RETURN ((v_prix_actuel - v_prix_moyen_achat) / v_prix_moyen_achat * 100);
    ELSE
        RETURN 0;
    END IF;
END;
$$ LANGUAGE plpgsql;

-- --------------------------------------------------------
-- 4. TRIGGERS
-- --------------------------------------------------------

-- Trigger: maj_solde_utilisateur
-- Description: Met à jour le solde utilisateur après un dépôt ou retrait
CREATE OR REPLACE FUNCTION maj_solde_utilisateur() RETURNS TRIGGER AS $$
BEGIN
    IF NEW.type_action IN ('depot', 'retrait') THEN
        UPDATE portefeuille
        SET solde_utilisateur = 
            CASE 
                WHEN NEW.type_action = 'depot' THEN solde_utilisateur + NEW.montant
                WHEN NEW.type_action = 'retrait' THEN solde_utilisateur - NEW.montant
            END
        WHERE id = NEW.id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_maj_solde_utilisateur
AFTER INSERT ON historique_utilisateur
FOR EACH ROW
EXECUTE FUNCTION maj_solde_utilisateur();

-- --------------------------------------------------------
-- 5. INDEX
-- --------------------------------------------------------

-- Index sur les dates pour optimiser les recherches temporelles
CREATE INDEX IF NOT EXISTS idx_date_variation ON variationcrypto(date_variation);
CREATE INDEX IF NOT EXISTS idx_date_action_pf ON portefeuille_fille(date_action);
CREATE INDEX IF NOT EXISTS idx_date_action_ht ON historique_transactions(date_action);

-- Index sur les clés étrangères pour optimiser les jointures
CREATE INDEX IF NOT EXISTS idx_id_crypto_pf ON portefeuille_fille(id_crypto);
CREATE INDEX IF NOT EXISTS idx_id_crypto_ht ON historique_transactions(id_crypto);
CREATE INDEX IF NOT EXISTS idx_id_portefeuille_pf ON portefeuille_fille(id_portefeuille);
CREATE INDEX IF NOT EXISTS idx_id_portefeuille_ht ON historique_transactions(id_portefeuille);
