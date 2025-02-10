-- Fonction pour effectuer un achat et enregistrer dans historique_transactions
CREATE OR REPLACE FUNCTION effectuer_achat(
    p_id_portefeuille INTEGER,
    p_id_crypto INTEGER,
    p_nbr_crypto INTEGER,
    p_prix_unitaire NUMERIC
) RETURNS TABLE (
    prix_unitaire NUMERIC,
    quantite INTEGER,
    prix_total NUMERIC,
    taux_commission NUMERIC,
    montant_commission NUMERIC,
    montant_final NUMERIC
) AS $$
DECLARE
    v_prix_total NUMERIC;
    v_taux_commission NUMERIC;
    v_montant_commission NUMERIC;
    v_montant_final NUMERIC;
BEGIN
    -- Calculer le prix total avant commission
    v_prix_total := p_nbr_crypto * p_prix_unitaire;

    -- Récupérer le taux de commission d'achat
    SELECT taux_achat INTO v_taux_commission
    FROM commission
    ORDER BY date_modification DESC
    LIMIT 1;

    IF v_taux_commission IS NULL THEN
        RAISE EXCEPTION 'Aucun taux de commission trouvé';
    END IF;

    -- Calculer la commission et le montant final
    v_montant_commission := (v_prix_total * v_taux_commission) / 100;
    v_montant_final := v_prix_total + v_montant_commission;

    -- Vérifier si l'utilisateur a assez d'argent
    IF (SELECT solde_utilisateur FROM portefeuille WHERE id = p_id_portefeuille) < v_montant_final THEN
        RAISE EXCEPTION 'Solde insuffisant pour cet achat';
    END IF;

    -- Insérer dans portefeuille_fille
    INSERT INTO portefeuille_fille (
        id_portefeuille,
        id_crypto,
        id_action,
        date_action,
        nbr_crypto,
        prix_achat,
        prix_total_crypto,
        taux_commission,
        montant_commission,
        prix_total_avec_commission
    ) VALUES (
        p_id_portefeuille,
        p_id_crypto,
        4, -- 4 = achat
        NOW(),
        p_nbr_crypto,
        p_prix_unitaire,
        v_prix_total::TEXT,
        v_taux_commission,
        v_montant_commission,
        v_montant_final
    );

    -- Mettre à jour le solde de l'utilisateur
    UPDATE portefeuille
    SET solde_utilisateur = solde_utilisateur - v_montant_final
    WHERE id = p_id_portefeuille;

    -- Insérer dans historique_transactions
    INSERT INTO historique_transactions (
        id_portefeuille,
        id_crypto,
        nom_crypto,
        type_action,
        nbrcrypto,
        prix,
        prixtotal,
        taux_commission,
        montant_commission,
        prix_total_avec_commission,
        date_action
    )
    SELECT
        p_id_portefeuille,
        p_id_crypto,
        c.nom_crypto,
        'achat',
        p_nbr_crypto,
        p_prix_unitaire,
        v_prix_total,
        v_taux_commission,
        v_montant_commission,
        v_montant_final,
        NOW()
    FROM crypto c
    WHERE c.id_crypto = p_id_crypto;

    -- Retourner les détails de l'achat
    RETURN QUERY SELECT
        p_prix_unitaire::NUMERIC as prix_unitaire,
        p_nbr_crypto as quantite,
        v_prix_total as prix_total,
        v_taux_commission as taux_commission,
        v_montant_commission as montant_commission,
        v_montant_final as montant_final;
END;
$$ LANGUAGE plpgsql;
