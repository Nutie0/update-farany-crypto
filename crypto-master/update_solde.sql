-- Mettre à jour la fonction pour vérifier le statut
CREATE OR REPLACE FUNCTION update_solde_portefeuille()
RETURNS trigger AS $$
BEGIN
    -- Ne mettre à jour le solde que si le statut est 'approuve'
    IF NEW.statut = 'approuve' THEN
        -- Log the action and amount
        RAISE NOTICE 'Trigger called with id_action = % and somme = %', NEW.id_action, NEW.somme;

        -- Check if the action is a deposit (1)
        IF NEW.id_action = 1 THEN
            UPDATE portefeuille
            SET solde_utilisateur = solde_utilisateur + NEW.somme
            WHERE id = NEW.id;
            RAISE NOTICE 'Deposit completed';

        -- Check if the action is a withdrawal (2)
        ELSIF NEW.id_action = 2 THEN
            -- Check if the balance is sufficient before withdrawal
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
$$ LANGUAGE plpgsql;
