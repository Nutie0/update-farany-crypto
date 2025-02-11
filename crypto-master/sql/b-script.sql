CREATE OR REPLACE VIEW liste_achat AS
SELECT pf.id_portefeuille_fille,
    pf.id_crypto,
    pf.id_portefeuille,
    c.nom_crypto,
    pf.nbr_crypto,
    pf.prix_achat,
    pf.prix_total_avec_commission AS prix_total_crypto,
    pf.date_action,
    pf.montant_commission,
    pf.taux_commission
FROM portefeuille_fille pf
    JOIN crypto c ON c.id_crypto = pf.id_crypto
WHERE pf.id_action = 4;



CREATE OR REPLACE VIEW vue_positions_achat AS
 SELECT pf.id_portefeuille_fille,
    pf.id_crypto,
    pf.id_portefeuille,
    pf.id_action,
    pf.date_action,
    pf.nbr_crypto,
    pf.prix_total_crypto,
    pf.prix_achat,
    pf.montant_commission,
    pf.taux_commission,
    pf.prix_total_avec_commission,
    c.nom_crypto,
    p.solde_utilisateur,
    COALESCE(v.prixevoluer::double precision, pf.prix_achat) AS prix_actuel,
        CASE
            WHEN pf.prix_achat > 0::double precision AND v.prixevoluer IS NOT NULL THEN round(((v.prixevoluer::double precision - pf.prix_achat) / pf.prix_achat * 100::double precision)::numeric, 2)
            ELSE 0::numeric
        END AS pourcentage_evolution,
    pf.nbr_crypto::double precision * COALESCE(v.prixevoluer::double precision, pf.prix_achat) AS prix_total
   FROM portefeuille_fille pf
     JOIN crypto c ON c.id_crypto = pf.id_crypto
     JOIN portefeuille p ON p.id = pf.id_portefeuille
     LEFT JOIN LATERAL ( SELECT DISTINCT ON (variationcrypto.id_crypto) variationcrypto.id_crypto,
            variationcrypto.prixevoluer,
            variationcrypto.date_variation
           FROM variationcrypto
          ORDER BY variationcrypto.id_crypto, variationcrypto.date_variation DESC) v ON v.id_crypto = pf.id_crypto
  WHERE pf.id_action = 4;





-- Create the function to calculate the average purchase price
CREATE OR REPLACE FUNCTION calculer_prix_moyen_achat
(
    p_id_utilisateur INTEGER,
    p_id_crypto INTEGER
)
RETURNS NUMERIC AS $$
DECLARE
    v_total_achats NUMERIC;
    v_total_quantite NUMERIC;
BEGIN
    -- Calculate the total purchase amount and quantity
    SELECT
        SUM(pf.prix_total_avec_commission),
        SUM(pf.nbr_crypto)
    INTO
        v_total_achats
    ,
        v_total_quantite
    FROM
        portefeuille p
    JOIN
        portefeuille_fille pf ON p.id = pf.id_portefeuille
    WHERE
        p.id_utilisateur = p_id_utilisateur
        AND pf.id_crypto = p_id_crypto
        AND pf.type_action = 'achat';

    -- Return the average purchase price or 0 if no purchases
    IF v_total_quantite > 0 THEN
    RETURN v_total_achats / v_total_quantite;
    ELSE
    RETURN 0;
END
IF;
END;
$$ LANGUAGE plpgsql
VOLATILE
SECURITY INVOKER;


-- Create the function to calculate the return on investment (ROI) for a cryptocurrency
CREATE OR REPLACE FUNCTION calculer_rendement_crypto
(
    p_id_utilisateur INTEGER,
    p_id_crypto INTEGER
)
RETURNS NUMERIC AS $$
DECLARE
    v_prix_moyen_achat NUMERIC;
    v_prix_actuel NUMERIC;
BEGIN
    -- Retrieve the average purchase price
    v_prix_moyen_achat := calculer_prix_moyen_achat
(p_id_utilisateur, p_id_crypto);

-- Retrieve the latest price
SELECT prixevoluer
INTO v_prix_actuel
FROM variationcrypto
WHERE id_crypto = p_id_crypto
ORDER BY date_variation DESC
    LIMIT 1;

    -- Calculate the ROI
    IF v_prix_moyen_achat
> 0 THEN
RETURN ((v_prix_actuel - v_prix_moyen_achat) / v_prix_moyen_achat * 100);
ELSE
RETURN 0;
END
IF;
END;
$$ LANGUAGE plpgsql
VOLATILE
SECURITY INVOKER;



-- Create the function to perform a cryptocurrency purchase
CREATE OR REPLACE FUNCTION effectuer_achat(
    p_id_portefeuille INTEGER,
    p_id_crypto INTEGER,
    p_nbr_crypto INTEGER,
    p_prix_unitaire NUMERIC
)
RETURNS TABLE(
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
    -- Calculate the total price before commission
    v_prix_total := p_nbr_crypto * p_prix_unitaire;

    -- Retrieve the purchase commission rate
    SELECT taux_achat INTO v_taux_commission
    FROM commission
    ORDER BY date_modification DESC
    LIMIT 1;

    IF v_taux_commission IS NULL THEN
        RAISE EXCEPTION 'Aucun taux de commission trouvé';
    END IF;

    -- Calculate the commission and final amount
    v_montant_commission := (v_prix_total * v_taux_commission) / 100;
    v_montant_final := v_prix_total + v_montant_commission;

    -- Check if the user has sufficient balance
    IF (SELECT solde_utilisateur FROM portefeuille WHERE id = p_id_portefeuille) < v_montant_final THEN
        RAISE EXCEPTION 'Solde insuffisant pour cet achat';
    END IF;

    -- Insert into portefeuille_fille
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

    -- Update the user's balance
    UPDATE portefeuille
    SET solde_utilisateur = solde_utilisateur - v_montant_final
    WHERE id = p_id_portefeuille;

    -- Insert into historique_transactions
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

    -- Return the purchase details
    RETURN QUERY SELECT
        p_prix_unitaire::NUMERIC AS prix_unitaire,
        p_nbr_crypto AS quantite,
        v_prix_total AS prix_total,
        v_taux_commission AS taux_commission,
        v_montant_commission AS montant_commission,
        v_montant_final AS montant_final;
END;
$$ LANGUAGE plpgsql
VOLATILE
SECURITY INVOKER;

-- Create the function to perform a cryptocurrency sale
CREATE OR REPLACE FUNCTION effectuer_vente(
    p_idportefeuillefille INTEGER,
    p_nbr_a_vendre INTEGER
)
RETURNS TABLE(
    prix_unitaire NUMERIC,
    quantite INTEGER,
    prix_total NUMERIC,
    taux_commission NUMERIC,
    montant_commission NUMERIC,
    montant_final NUMERIC
) AS $$
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
    -- Retrieve purchase details
    SELECT pf.id_portefeuille, pf.id_crypto, pf.nbr_crypto, pf.prix_achat
    INTO v_id_portefeuille, v_id_crypto, v_nbr_crypto_actuel, v_prix_achat
    FROM portefeuille_fille pf
    WHERE pf.id_portefeuille_fille = p_idportefeuillefille
      AND pf.id_action = 4;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Operation d achat introuvable pour id_portefeuille_fille = %', p_idportefeuillefille;
    END IF;

    -- Validate quantity
    IF p_nbr_a_vendre <= 0 OR p_nbr_a_vendre > v_nbr_crypto_actuel THEN
        RAISE EXCEPTION 'Nombre de crypto a vendre (%), invalide. Il doit etre compris entre 1 et %', p_nbr_a_vendre, v_nbr_crypto_actuel;
    END IF;

    -- Retrieve latest price
    SELECT vtmp.prixevoluer
    INTO v_prixevoluer
    FROM variationcrypto vtmp
    WHERE vtmp.id_crypto = v_id_crypto
    ORDER BY vtmp.date_variation DESC
    LIMIT 1;

    IF v_prixevoluer IS NULL THEN
        RAISE EXCEPTION 'Aucune variation trouvee pour le crypto id = %', v_id_crypto;
    END IF;

    -- Retrieve commission rate
    SELECT taux_vente
    INTO v_taux_commission
    FROM commission
    ORDER BY date_modification DESC
    LIMIT 1;

    IF v_taux_commission IS NULL THEN
        RAISE EXCEPTION 'Aucun taux de commission trouve';
    END IF;

    -- Calculate sale amount and commission
    v_prixtotalvente := p_nbr_a_vendre * v_prixevoluer;
    v_montant_commission := (v_prixtotalvente * v_taux_commission) / 100;
    v_montant_final := v_prixtotalvente - v_montant_commission;

    -- Update user balance
    UPDATE portefeuille
    SET solde_utilisateur = solde_utilisateur + v_montant_final
    WHERE id = v_id_portefeuille;

    -- Update purchase entry
    UPDATE portefeuille_fille
    SET nbr_crypto = nbr_crypto - p_nbr_a_vendre
    WHERE id_portefeuille_fille = p_idportefeuillefille;

    -- Insert into transaction history
    INSERT INTO historique_transactions (
        id_portefeuille, id_crypto, nom_crypto, type_action, nbrcrypto, prix, prixtotal,
        taux_commission, montant_commission, prix_total_avec_commission, date_action
    )
    SELECT
        p.id,
        c.id_crypto,
        c.nom_crypto,
        'vente',
        p_nbr_a_vendre,
        v_prixevoluer,
        v_prixtotalvente,
        v_taux_commission,
        v_montant_commission,
        v_montant_final,
        NOW()
    FROM portefeuille p
    JOIN crypto c ON c.id_crypto = v_id_crypto
    WHERE p.id = v_id_portefeuille;

    -- Return sale details
    RETURN QUERY SELECT
        v_prixevoluer AS prix_unitaire,
        p_nbr_a_vendre AS quantite,
        v_prixtotalvente AS prix_total,
        v_taux_commission AS taux_commission,
        v_montant_commission AS montant_commission,
        v_montant_final AS montant_final;

    RAISE NOTICE 'Vente effectuee :
         Quantite: % crypto
         Prix unitaire: %
         Prix total avant commission: %
         Commission (% %): %
         Montant final: %',
         p_nbr_a_vendre, v_prixevoluer, v_prixtotalvente,
         v_taux_commission, '%', v_montant_commission, v_montant_final;
END;
$$ LANGUAGE plpgsql
VOLATILE
SECURITY INVOKER;


-- Create the function to calculate the cryptocurrency balance
CREATE OR REPLACE FUNCTION calculer_solde_crypto(
    p_id_utilisateur INTEGER,
    p_id_crypto INTEGER
)
RETURNS NUMERIC AS $$
BEGIN
    RETURN (
        SELECT
            COALESCE(SUM(CASE
                WHEN pf.type_action = 'achat' THEN pf.nbr_crypto
                WHEN ht.type_action = 'vente' THEN -ht.nbrcrypto
                ELSE 0
            END), 0)
        FROM
            portefeuille p
        LEFT JOIN
            portefeuille_fille pf ON p.id = pf.id_portefeuille AND pf.id_crypto = p_id_crypto
        LEFT JOIN
            historique_transactions ht ON p.id = ht.id_portefeuille AND ht.id_crypto = p_id_crypto
        WHERE
            p.id_utilisateur = p_id_utilisateur
    );
END;
$$ LANGUAGE plpgsql
VOLATILE
SECURITY INVOKER;



CREATE OR REPLACE FUNCTION historique_transactions_utilisateur(
    p_id_utilisateur INTEGER DEFAULT NULL,
    p_id_crypto INTEGER DEFAULT NULL,
    p_date_debut TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL,
    p_date_fin TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL
)
RETURNS TABLE(
    email CHARACTER VARYING,
    date_action TIMESTAMP WITHOUT TIME ZONE,
    nom_crypto CHARACTER VARYING,
    type_action CHARACTER VARYING,
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
        END AS remarque
    FROM
        historique_transactions ht
    JOIN
        portefeuille p ON ht.id_portefeuille = p.id
    JOIN
        utilisateur u ON p.id_utilisateur = u.id_utilisateur
    JOIN
        crypto c ON ht.id_crypto = c.id_crypto
    WHERE
        (p_id_utilisateur IS NULL OR p.id_utilisateur = p_id_utilisateur)
        AND (p_id_crypto IS NULL OR ht.id_crypto = p_id_crypto)
        AND (p_date_debut IS NULL OR ht.date_action >= p_date_debut)
        AND (p_date_fin IS NULL OR ht.date_action <= p_date_fin)
    ORDER BY
        ht.date_action DESC;
END;
$$ LANGUAGE plpgsql
VOLATILE
SECURITY INVOKER;


-- Create the function to update crypto variations

CREATE OR REPLACE FUNCTION update_crypto_variation()
RETURNS void AS $$
DECLARE
    rec RECORD; -- Record to iterate through each crypto
    variation DECIMAL(5,4); -- Price variation percentage
    prix_evolue DECIMAL(15,2); -- New price after variation
BEGIN
    -- Loop through all cryptocurrencies
    FOR rec IN SELECT id_crypto, prix_initiale_crypto FROM crypto LOOP
        -- Retrieve the latest evolved price
        SELECT prixevoluer INTO prix_evolue
        FROM variationcrypto
        WHERE id_crypto = rec.id_crypto
        ORDER BY date_variation DESC
        LIMIT 1;

        -- If no previous variation, use the initial price
        IF prix_evolue IS NULL THEN
            prix_evolue := rec.prix_initiale_crypto;
        END IF;

        -- Calculate the variation
        variation := round((random() * 0.1 - 0.05)::numeric, 4); -- Variation between -5% and +5%
        prix_evolue := prix_evolue * (1 + variation); -- Apply the variation

        -- Insert the new variation
        INSERT INTO variationcrypto (pourcentagevariation, prixevoluer, date_variation, id_crypto)
        VALUES (variation * 100, prix_evolue, NOW(), rec.id_crypto);
    END LOOP;
END;
$$ LANGUAGE plpgsql
VOLATILE
SECURITY INVOKER;



-- Create the trigger function to update portfolio balance
CREATE OR REPLACE FUNCTION update_portefeuille_solde()
RETURNS TRIGGER AS $$
BEGIN
    -- Deposit: Add the amount
    IF NEW.id_action = 1 THEN
        UPDATE portefeuille
        SET solde_utilisateur = COALESCE(solde_utilisateur, 0) + CAST(NEW.somme AS DECIMAL(15,2))
        WHERE id = NEW.id;

    -- Withdrawal: Check if the balance is sufficient
    ELSIF NEW.id_action = 2 THEN
        IF (SELECT solde_utilisateur FROM portefeuille WHERE id = NEW.id) >= CAST(NEW.somme AS DECIMAL(15,2)) THEN
            UPDATE portefeuille
            SET solde_utilisateur = solde_utilisateur - CAST(NEW.somme AS DECIMAL(15,2))
            WHERE id = NEW.id;
        ELSE
            RAISE EXCEPTION 'Solde insuffisant';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql
VOLATILE
SECURITY INVOKER;





-- Create the trigger function to update portfolio balance
CREATE OR REPLACE FUNCTION update_solde_portefeuille()
RETURNS TRIGGER AS $$
BEGIN
    -- Only update the balance if the status is 'approuve'
    IF NEW.statut = 'approuve' THEN
        -- Log the action and amount
        RAISE NOTICE 'Trigger called with id_action = % and somme = %', NEW.id_action, NEW.somme;

        -- Deposit: Add the amount
        IF NEW.id_action = 1 THEN
            UPDATE portefeuille
            SET solde_utilisateur = solde_utilisateur + NEW.somme
            WHERE id = NEW.id;
            RAISE NOTICE 'Deposit completed';

        -- Withdrawal: Check if the balance is sufficient
        ELSIF NEW.id_action = 2 THEN
            IF (SELECT solde_utilisateur FROM portefeuille WHERE id = NEW.id) >= NEW.somme THEN
                UPDATE portefeuille
                SET solde_utilisateur = solde_utilisateur - NEW.somme
                WHERE id = NEW.id;
                RAISE NOTICE 'Withdrawal completed';
            ELSE
                RAISE EXCEPTION 'Solde insuffisant';
            END IF;
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql
VOLATILE
SECURITY INVOKER;