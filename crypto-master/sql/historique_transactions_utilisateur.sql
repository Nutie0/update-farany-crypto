CREATE OR REPLACE FUNCTION historique_transactions_utilisateur(
    p_id_utilisateur INTEGER = NULL,
    p_id_crypto INTEGER = NULL,
    p_date_debut TIMESTAMP = NULL,
    p_date_fin TIMESTAMP = NULL
) RETURNS TABLE (
    email VARCHAR,
    date_action TIMESTAMP,
    nom_crypto VARCHAR,
    type_action VARCHAR,
    nbrcrypto NUMERIC,
    prix NUMERIC,
    prixtotal NUMERIC,
    remarque TEXT
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        u.email,
        ht.date_action,
        c.nom_crypto,
        ht.type_action,
        ht.nbrcrypto,
        ht.prix,
        ht.prixtotal,
        CASE 
            WHEN p_id_utilisateur IS NOT NULL AND p.id_utilisateur = p_id_utilisateur THEN 'moi'
            ELSE ''
        END as remarque
    FROM historique_transactions ht
    JOIN portefeuille p ON ht.id_portefeuille = p.id
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    JOIN crypto c ON ht.id_crypto = c.id_crypto
    WHERE 
        (p_id_utilisateur IS NULL OR p.id_utilisateur = p_id_utilisateur)
        AND (p_id_crypto IS NULL OR ht.id_crypto = p_id_crypto)
        AND (p_date_debut IS NULL OR ht.date_action >= p_date_debut)
        AND (p_date_fin IS NULL OR ht.date_action <= p_date_fin)
    ORDER BY ht.date_action DESC;
END;
$$ LANGUAGE plpgsql;
