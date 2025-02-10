WITH transaction_details AS (
    -- Première étape : Calculer les montants par crypto pour chaque utilisateur
    SELECT 
        p.id_utilisateur,
        u.nom as user_name,
        COALESCE(pf.id_crypto, 0) as id_crypto,
        SUM(CASE WHEN ap.type_action = 'achat' THEN pf.prix_total_avec_commission::numeric ELSE 0 END) as montant_achat,
        SUM(CASE WHEN ap.type_action = 'vente' THEN ht.prix_total_avec_commission ELSE 0 END) as montant_vente,
        SUM(CASE WHEN ap.type_action = 'achat' THEN pf.nbr_crypto ELSE 0 END) as nbr_achete,
        SUM(CASE WHEN ap.type_action = 'vente' THEN ht.nbrcrypto ELSE 0 END) as nbr_vendu
    FROM portefeuille p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille
    LEFT JOIN action_portefeuille ap ON ht.type_action = ap.type_action
    LEFT JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille 
        AND pf.date_action <= ?
        AND ap.type_action = 'achat'
    WHERE (ht.date_action IS NULL OR ht.date_action <= ?)
    GROUP BY p.id_utilisateur, u.nom, pf.id_crypto
),
latest_prices AS (
    -- Deuxième étape : Obtenir le dernier prix pour chaque crypto
    SELECT DISTINCT ON (id_crypto) 
        id_crypto,
        prixevoluer as dernier_prix
    FROM variationcrypto
    WHERE date_variation <= ?
    ORDER BY id_crypto, date_variation DESC
),
user_totals AS (
    -- Troisième étape : Calculer les totaux par utilisateur
    SELECT 
        td.id_utilisateur,
        td.user_name,
        SUM(td.montant_achat) as total_achat,
        SUM(td.montant_vente) as total_vente,
        SUM((td.nbr_achete - td.nbr_vendu) * COALESCE(lp.dernier_prix, 0)) as valeur_portefeuille
    FROM transaction_details td
    LEFT JOIN latest_prices lp ON td.id_crypto = lp.id_crypto
    GROUP BY td.id_utilisateur, td.user_name
)
SELECT 
    id_utilisateur,
    user_name,
    total_achat,
    total_vente,
    valeur_portefeuille,
    CASE 
        WHEN total_achat > 0 THEN 
            ((valeur_portefeuille + total_vente - total_achat) / total_achat * 100)
        ELSE 0 
    END as rendement_pourcentage
FROM user_totals
ORDER BY user_name;
