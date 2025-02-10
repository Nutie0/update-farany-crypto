CREATE OR REPLACE VIEW vue_positions_achat AS
SELECT 
    pf.id_crypto || '_' || pf.id || '_' || pf.id_action as idportefeuillefille,
    pf.id,
    c.nom_crypto,
    pf.date_action,
    pf.nbr_crypto,
    pf.prix_achat,
    v.prixevoluer AS prix_actuel,
    pf.nbr_crypto * v.prixevoluer AS prix_total,
    ROUND(((v.prixevoluer - pf.prix_achat) * 100) / pf.prix_achat, 2) AS pourcentage_evolution
FROM portefeuille_fille pf
JOIN crypto c ON pf.id_crypto = c.id_crypto
JOIN action_portefeuille a ON pf.id_action = a.id_action
JOIN LATERAL (
    SELECT vtmp.prixevoluer
    FROM variationcrypto vtmp
    WHERE vtmp.id_crypto = c.id_crypto
    ORDER BY vtmp.date_variation DESC
    LIMIT 1
) v ON true
WHERE a.id_action = 4;
