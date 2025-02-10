CREATE OR REPLACE FUNCTION public.effectuer_vente(p_idportefeuillefille integer, p_nbr_a_vendre integer)
 RETURNS TABLE(prix_unitaire numeric, quantite integer, prix_total numeric, taux_commission numeric, montant_commission numeric, montant_final numeric)
 LANGUAGE plpgsql
AS $function$
DECLARE
    v_id_portefeuille INT;
    v_id_crypto INT;
    v_nbr_crypto_actuel INT;
    v_prix_achat NUMERIC(15,2);
    v_prixevoluer NUMERIC(15,2);
    v_prixtotalvente NUMERIC(15,2);
    v_taux_commission NUMERIC(5,2);
    v_montant_commission NUMERIC(15,2);
    v_montant_final NUMERIC(15,2);
BEGIN
    -- Recuperer la ligne d achat dans portefeuille_fille
    SELECT pf.id_portefeuille, pf.id_crypto, pf.nbr_crypto, pf.prix_achat
    INTO v_id_portefeuille, v_id_crypto, v_nbr_crypto_actuel, v_prix_achat
    FROM portefeuille_fille pf
    WHERE pf.id_portefeuille_fille = p_idportefeuillefille
      AND pf.id_action = 4;

    IF NOT FOUND THEN
       RAISE EXCEPTION 'Operation d achat introuvable pour id_portefeuille_fille = %', p_idportefeuillefille;
    END IF;

    -- Verifier que le nombre demande est coherent
    IF p_nbr_a_vendre <= 0 OR p_nbr_a_vendre > v_nbr_crypto_actuel THEN
       RAISE EXCEPTION 'Nombre de crypto a vendre (%), invalide. Il doit etre compris entre 1 et %', p_nbr_a_vendre, v_nbr_crypto_actuel;
    END IF;

    -- Recuperer le dernier prix evolue du crypto
    SELECT vtmp.prixevoluer
    INTO v_prixevoluer
    FROM variationcrypto vtmp
    WHERE vtmp.id_crypto = v_id_crypto
    ORDER BY vtmp.date_variation DESC
    LIMIT 1;

    IF v_prixevoluer IS NULL THEN
       RAISE EXCEPTION 'Aucune variation trouvee pour le crypto id = %', v_id_crypto;
    END IF;

    -- Recuperer le taux de commission de vente
    SELECT taux_vente
    INTO v_taux_commission
    FROM commission
    ORDER BY date_modification DESC
    LIMIT 1;

    IF v_taux_commission IS NULL THEN
       RAISE EXCEPTION 'Aucun taux de commission trouve';
    END IF;

    -- Calculer le montant total de la vente et la commission
    v_prixtotalvente := p_nbr_a_vendre * v_prixevoluer;
    v_montant_commission := (v_prixtotalvente * v_taux_commission) / 100;
    v_montant_final := v_prixtotalvente - v_montant_commission;

    -- Mettre a jour le solde de l utilisateur
    UPDATE portefeuille
    SET solde_utilisateur = solde_utilisateur + v_montant_final
    WHERE id = v_id_portefeuille;

    -- Mettre a jour la ligne d achat
    UPDATE portefeuille_fille
    SET nbr_crypto = nbr_crypto - p_nbr_a_vendre
    WHERE id_portefeuille_fille = p_idportefeuillefille;

    -- Inserer l operation de vente dans l historique
    INSERT INTO historique_transactions 
    (id_portefeuille, id_crypto, nom_crypto, type_action, nbrcrypto, prix, prixtotal, 
     taux_commission, montant_commission, prix_total_avec_commission, date_action)
    SELECT 
        p.id,
        c.id_crypto,
        c.nom_crypto, 
        a.type_action, 
        p_nbr_a_vendre, 
        v_prixevoluer, 
        v_prixtotalvente,
        v_taux_commission,
        v_montant_commission,
        v_montant_final,
        NOW()
    FROM portefeuille p
    JOIN crypto c ON c.id_crypto = v_id_crypto
    JOIN action_portefeuille a ON a.id_action = 3
    WHERE p.id = v_id_portefeuille;

    -- Retourner les details de la vente
    RETURN QUERY SELECT 
        v_prixevoluer as prix_unitaire,
        p_nbr_a_vendre as quantite,
        v_prixtotalvente as prix_total,
        v_taux_commission as taux_commission,
        v_montant_commission as montant_commission,
        v_montant_final as montant_final;

    RAISE NOTICE 'Vente effectuee : 
        Quantite: % crypto
        Prix unitaire: % 
        Prix total avant commission: %
        Commission (% %): %
        Montant final: %', 
        p_nbr_a_vendre, v_prixevoluer, v_prixtotalvente,
        v_taux_commission, '%', v_montant_commission, v_montant_final;
END;
$function$;
