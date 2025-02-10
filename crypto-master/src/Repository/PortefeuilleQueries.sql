-- =============================================
-- Requêtes pour le Portefeuille et Transactions
-- =============================================

-- 1. Vue d'ensemble du portefeuille d'un utilisateur
-- ------------------------------------------------
WITH solde_details AS (
    SELECT 
        p.id_utilisateur,
        u.nom as user_name,
        p.solde_utilisateur as solde_actuel,
        COALESCE(SUM(CASE WHEN ap.type_action = 'depot' THEN hu.montant ELSE 0 END), 0) as total_depots,
        COALESCE(SUM(CASE WHEN ap.type_action = 'retrait' THEN hu.montant ELSE 0 END), 0) as total_retraits
    FROM portefeuille p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN historique_utilisateur hu ON p.id = hu.id
    LEFT JOIN action_portefeuille ap ON hu.type_action = ap.type_action
    GROUP BY p.id_utilisateur, u.nom, p.solde_utilisateur
)
SELECT * FROM solde_details;

-- 2. Détails des transactions crypto par utilisateur
-- ------------------------------------------------
WITH crypto_transactions AS (
    SELECT 
        p.id_utilisateur,
        u.nom as user_name,
        c.nom_crypto,
        SUM(CASE WHEN ap.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END) as total_achete,
        SUM(CASE WHEN ap.type_action = 'vente' THEN ht.nbrcrypto ELSE 0 END) as total_vendu,
        SUM(CASE WHEN ap.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END) - 
        SUM(CASE WHEN ap.type_action = 'vente' THEN ht.nbrcrypto ELSE 0 END) as solde_crypto,
        SUM(CASE WHEN ap.type_action = 'achat' THEN pf.prix_total_avec_commission ELSE 0 END) as montant_achats,
        SUM(CASE WHEN ap.type_action = 'vente' THEN ht.prix_total_avec_commission ELSE 0 END) as montant_ventes
    FROM portefeuille p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille
    LEFT JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
    LEFT JOIN action_portefeuille ap ON COALESCE(ht.type_action, pf.type_action) = ap.type_action
    LEFT JOIN crypto c ON COALESCE(pf.id_crypto, ht.id_crypto) = c.id_crypto
    GROUP BY p.id_utilisateur, u.nom, c.nom_crypto
    HAVING c.nom_crypto IS NOT NULL
)
SELECT * FROM crypto_transactions ORDER BY user_name, nom_crypto;

-- 3. Analyse des commissions
-- -------------------------
WITH commission_analysis AS (
    SELECT 
        p.id_utilisateur,
        u.nom as user_name,
        c.nom_crypto,
        ap.type_action,
        COUNT(*) as nombre_transactions,
        AVG(CASE 
            WHEN ap.type_action = 'achat' THEN pf.taux_commission 
            ELSE ht.taux_commission 
        END) as taux_commission_moyen,
        SUM(CASE 
            WHEN ap.type_action = 'achat' THEN pf.montant_commission 
            ELSE ht.montant_commission 
        END) as total_commissions
    FROM portefeuille p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille
    LEFT JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
    LEFT JOIN action_portefeuille ap ON COALESCE(ht.type_action, pf.type_action) = ap.type_action
    LEFT JOIN crypto c ON COALESCE(pf.id_crypto, ht.id_crypto) = c.id_crypto
    WHERE ap.type_action IN ('achat', 'vente')
    GROUP BY p.id_utilisateur, u.nom, c.nom_crypto, ap.type_action
    HAVING c.nom_crypto IS NOT NULL
)
SELECT * FROM commission_analysis ORDER BY user_name, nom_crypto, type_action;

-- 4. Performance du portefeuille
-- -----------------------------
WITH performance_details AS (
    SELECT 
        p.id_utilisateur,
        u.nom as user_name,
        c.nom_crypto,
        -- Calcul des quantités
        SUM(CASE WHEN ap.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END) - 
        SUM(CASE WHEN ap.type_action = 'vente' THEN ht.nbrcrypto ELSE 0 END) as quantite_actuelle,
        -- Prix moyen d'achat
        CASE 
            WHEN SUM(CASE WHEN ap.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END) > 0 
            THEN SUM(CASE WHEN ap.type_action = 'achat' THEN pf.prix_total_avec_commission ELSE 0 END) / 
                 SUM(CASE WHEN ap.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END)
            ELSE 0 
        END as prix_moyen_achat,
        -- Prix actuel
        (SELECT prixevoluer 
         FROM variationcrypto v 
         WHERE v.id_crypto = c.id_crypto 
         ORDER BY date_variation DESC 
         LIMIT 1) as prix_actuel
    FROM portefeuille p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille
    LEFT JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
    LEFT JOIN action_portefeuille ap ON COALESCE(ht.type_action, pf.type_action) = ap.type_action
    LEFT JOIN crypto c ON COALESCE(pf.id_crypto, ht.id_crypto) = c.id_crypto
    GROUP BY p.id_utilisateur, u.nom, c.nom_crypto, c.id_crypto
    HAVING c.nom_crypto IS NOT NULL
)
SELECT 
    *,
    CASE 
        WHEN prix_moyen_achat > 0 
        THEN ((prix_actuel - prix_moyen_achat) / prix_moyen_achat * 100)
        ELSE 0 
    END as rendement_pourcentage
FROM performance_details
ORDER BY user_name, nom_crypto;

-- 5. Historique des mouvements de solde
-- ------------------------------------
SELECT 
    p.id_utilisateur,
    u.nom as user_name,
    hu.date_action,
    ap.type_action,
    hu.montant,
    hu.solde_avant,
    hu.solde_apres,
    CASE 
        WHEN ap.type_action = 'depot' THEN 'Dépôt effectué'
        WHEN ap.type_action = 'retrait' THEN 'Retrait effectué'
        ELSE 'Autre opération'
    END as description
FROM portefeuille p
JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
JOIN historique_utilisateur hu ON p.id = hu.id
JOIN action_portefeuille ap ON hu.type_action = ap.type_action
ORDER BY u.nom, hu.date_action DESC;

-- 6. Vue consolidée du portefeuille
-- --------------------------------
WITH latest_prices AS (
    SELECT DISTINCT ON (id_crypto) 
        id_crypto,
        prixevoluer as dernier_prix
    FROM variationcrypto
    ORDER BY id_crypto, date_variation DESC
),
portefeuille_consolide AS (
    SELECT 
        p.id_utilisateur,
        u.nom as user_name,
        p.solde_utilisateur,
        COALESCE(SUM(CASE WHEN ap.type_action = 'achat' THEN pf.prix_total_avec_commission ELSE 0 END), 0) as total_investi,
        COALESCE(SUM(CASE WHEN ap.type_action = 'vente' THEN ht.prix_total_avec_commission ELSE 0 END), 0) as total_vendu,
        COALESCE(SUM(
            (COALESCE(SUM(CASE WHEN ap.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END), 0) - 
             COALESCE(SUM(CASE WHEN ap.type_action = 'vente' THEN ht.nbrcrypto ELSE 0 END), 0)) * lp.dernier_prix
        ), 0) as valeur_portefeuille_crypto
    FROM portefeuille p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille
    LEFT JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
    LEFT JOIN action_portefeuille ap ON COALESCE(ht.type_action, pf.type_action) = ap.type_action
    LEFT JOIN crypto c ON COALESCE(pf.id_crypto, ht.id_crypto) = c.id_crypto
    LEFT JOIN latest_prices lp ON c.id_crypto = lp.id_crypto
    GROUP BY p.id_utilisateur, u.nom, p.solde_utilisateur
)
SELECT 
    *,
    (solde_utilisateur + valeur_portefeuille_crypto) as valeur_totale,
    CASE 
        WHEN total_investi > 0 
        THEN (((solde_utilisateur + valeur_portefeuille_crypto + total_vendu) - total_investi) / total_investi * 100)
        ELSE 0 
    END as rendement_global_pourcentage
FROM portefeuille_consolide
ORDER BY user_name;
