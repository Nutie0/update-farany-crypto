-- Vue d'ensemble des commissions
WITH commission_overview AS (
    SELECT 
        COALESCE(SUM(CASE 
            WHEN ap.type_action IN ('achat', 'vente') THEN 
                COALESCE(pf.montant_commission, ht.montant_commission, 0)
            ELSE 0 
        END), 0) as total_commission,
        COUNT(*) as nb_transactions,
        COALESCE(AVG(CASE 
            WHEN ap.type_action IN ('achat', 'vente') THEN 
                COALESCE(pf.montant_commission, ht.montant_commission, 0)
            ELSE 0 
        END), 0) as commission_moyenne,
        COALESCE(MAX(CASE 
            WHEN ap.type_action IN ('achat', 'vente') THEN 
                COALESCE(pf.montant_commission, ht.montant_commission, 0)
            ELSE 0 
        END), 0) as plus_grosse_commission
    FROM action_portefeuille ap
    LEFT JOIN historique_transactions ht ON ht.type_action = ap.type_action
    LEFT JOIN portefeuille_fille pf ON pf.id_portefeuille = ht.id_portefeuille 
        AND pf.date_action = ht.date_action
    WHERE (ht.date_action BETWEEN ? AND ? OR pf.date_action BETWEEN ? AND ?)
    AND (? = 'tous' OR ht.nom_crypto = ?)
),

-- Évolution temporelle des commissions
evolution_temporelle AS (
    SELECT 
        DATE_TRUNC('day', COALESCE(ht.date_action, pf.date_action)) as date,
        SUM(CASE WHEN ap.type_action = 'achat' THEN COALESCE(pf.montant_commission, ht.montant_commission, 0) ELSE 0 END) as commission_achat,
        SUM(CASE WHEN ap.type_action = 'vente' THEN COALESCE(pf.montant_commission, ht.montant_commission, 0) ELSE 0 END) as commission_vente
    FROM action_portefeuille ap
    LEFT JOIN historique_transactions ht ON ht.type_action = ap.type_action
    LEFT JOIN portefeuille_fille pf ON pf.id_portefeuille = ht.id_portefeuille 
        AND pf.date_action = ht.date_action
    WHERE (ht.date_action BETWEEN ? AND ? OR pf.date_action BETWEEN ? AND ?)
    AND (? = 'tous' OR ht.nom_crypto = ?)
    GROUP BY DATE_TRUNC('day', COALESCE(ht.date_action, pf.date_action))
    ORDER BY date
),

-- Analyse par cryptomonnaie
crypto_analysis AS (
    SELECT 
        ht.nom_crypto as crypto,
        COUNT(*) as nb_transactions,
        SUM(COALESCE(pf.montant_commission, ht.montant_commission, 0)) as total_commission,
        AVG(COALESCE(pf.montant_commission, ht.montant_commission, 0)) as commission_moyenne,
        AVG(COALESCE(pf.montant_commission, ht.montant_commission, 0) / NULLIF(COALESCE(pf.montant, ht.montant), 0) * 100) as taux_moyen
    FROM action_portefeuille ap
    LEFT JOIN historique_transactions ht ON ht.type_action = ap.type_action
    LEFT JOIN portefeuille_fille pf ON pf.id_portefeuille = ht.id_portefeuille 
        AND pf.date_action = ht.date_action
    WHERE (ht.date_action BETWEEN ? AND ? OR pf.date_action BETWEEN ? AND ?)
    AND (? = 'tous' OR ht.nom_crypto = ?)
    GROUP BY ht.nom_crypto
    ORDER BY total_commission DESC
),

-- Distribution des commissions
commission_distribution AS (
    SELECT 
        CASE 
            WHEN montant < 10 THEN '0-10'
            WHEN montant < 50 THEN '10-50'
            WHEN montant < 100 THEN '50-100'
            WHEN montant < 500 THEN '100-500'
            ELSE '500+'
        END as tranche,
        COUNT(*) as nb_transactions
    FROM (
        SELECT 
            CASE 
                WHEN ap.type_action = 'achat' THEN COALESCE(pf.montant_commission, 0)
                WHEN ap.type_action = 'vente' THEN COALESCE(ht.montant_commission, 0)
                ELSE 0 
            END as montant
        FROM action_portefeuille ap
        LEFT JOIN historique_transactions ht ON ht.type_action = ap.type_action
        LEFT JOIN portefeuille_fille pf ON pf.id_portefeuille = ht.id_portefeuille 
            AND pf.date_action = ht.date_action
        WHERE (ht.date_action BETWEEN ? AND ? OR pf.date_action BETWEEN ? AND ?)
        AND (? = 'tous' OR ht.nom_crypto = ?)
    ) sub
    GROUP BY tranche
    ORDER BY tranche
)

SELECT json_build_object(
    'total_commission', (SELECT total_commission FROM commission_overview),
    'nb_transactions', (SELECT nb_transactions FROM commission_overview),
    'commission_moyenne', (SELECT commission_moyenne FROM commission_overview),
    'plus_grosse_commission', (SELECT plus_grosse_commission FROM commission_overview),
    'commission_par_type', (SELECT json_agg(row_to_json(t)) FROM (
        SELECT 
            type_action,
            SUM(CASE 
                WHEN type_action = 'achat' THEN COALESCE(pf.montant_commission, 0)
                WHEN type_action = 'vente' THEN COALESCE(ht.montant_commission, 0)
                ELSE 0 
            END) as total_commission
        FROM action_portefeuille ap
        LEFT JOIN historique_transactions ht ON ht.type_action = ap.type_action
        LEFT JOIN portefeuille_fille pf ON pf.id_portefeuille = ht.id_portefeuille 
            AND pf.date_action = ht.date_action
        WHERE (ht.date_action BETWEEN ? AND ? OR pf.date_action BETWEEN ? AND ?)
        AND (? = 'tous' OR ht.nom_crypto = ?)
        GROUP BY type_action
    ) t),
    'evolution_temporelle', COALESCE((SELECT json_agg(row_to_json(e)) FROM evolution_temporelle e), '[]'::json),
    'crypto_analysis', COALESCE((SELECT json_agg(row_to_json(c)) FROM crypto_analysis c), '[]'::json),
    'distribution', COALESCE((SELECT json_agg(row_to_json(d)) FROM commission_distribution d), '[]'::json)
) as data;
