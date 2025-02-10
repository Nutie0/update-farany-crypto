-- Exemple de calcul de SOMME vs MOYENNE des commissions
-- ================================================

-- Supposons ces transactions :
/*
Transaction 1 : Commission = 10€
Transaction 2 : Commission = 15€
Transaction 3 : Commission = 20€
*/

-- 1. Calcul de la SOMME
-- ---------------------
SELECT 
    c.nom_crypto,
    SUM(CASE 
        WHEN pf.montant_commission IS NOT NULL THEN pf.montant_commission
        ELSE ht.montant_commission 
    END) as somme_commissions
FROM portefeuille p
JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille
JOIN crypto c ON pf.id_crypto = c.id_crypto
WHERE p.id_utilisateur = 1  -- exemple pour l'utilisateur 1
GROUP BY c.nom_crypto;
-- Résultat : 10 + 15 + 20 = 45€ (TOTAL)


-- 2. Calcul de la MOYENNE
-- ----------------------
SELECT 
    c.nom_crypto,
    AVG(CASE 
        WHEN pf.montant_commission IS NOT NULL THEN pf.montant_commission
        ELSE ht.montant_commission 
    END) as moyenne_commissions
FROM portefeuille p
JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille
JOIN crypto c ON pf.id_crypto = c.id_crypto
WHERE p.id_utilisateur = 1  -- exemple pour l'utilisateur 1
GROUP BY c.nom_crypto;
-- Résultat : (10 + 15 + 20) / 3 = 15€ (MOYENNE par transaction)

-- La différence en un seul SELECT
-- ------------------------------
SELECT 
    c.nom_crypto,
    SUM(CASE 
        WHEN pf.montant_commission IS NOT NULL THEN pf.montant_commission
        ELSE ht.montant_commission 
    END) as somme_commissions,
    AVG(CASE 
        WHEN pf.montant_commission IS NOT NULL THEN pf.montant_commission
        ELSE ht.montant_commission 
    END) as moyenne_commissions,
    COUNT(*) as nombre_transactions
FROM portefeuille p
JOIN portefeuille_fille pf ON p.id = pf.id_portefeuille
LEFT JOIN historique_transactions ht ON p.id = ht.id_portefeuille
JOIN crypto c ON pf.id_crypto = c.id_crypto
WHERE p.id_utilisateur = 1
GROUP BY c.nom_crypto;

/*
Résultats pour Bitcoin (exemple) :
--------------------------------
nom_crypto | somme_commissions | moyenne_commissions | nombre_transactions
-----------+------------------+--------------------+-------------------
Bitcoin    |              45€ |                15€ |                 3

Interprétation :
- Somme : Le total de toutes les commissions (45€)
- Moyenne : La commission moyenne par transaction (15€)
- On peut voir que : somme_commissions = moyenne_commissions × nombre_transactions
*/
