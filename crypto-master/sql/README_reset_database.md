# Script de réinitialisation de la base de données

Ce script permet de réinitialiser complètement la base de données en :
1. Vidant toutes les tables
2. Réinitialisant toutes les séquences
3. Réinsérant les données de base nécessaires (cryptomonnaies)

## Utilisation

Pour exécuter le script, utilisez la commande suivante dans le terminal :

```bash
$env:PGPASSWORD='2004'; psql -U php -d crypto -f sql/reset_database.sql
```

## Fonctionnalités

Le script :
1. Désactive temporairement les contraintes de clés étrangères
2. Vide toutes les tables dans l'ordre approprié
3. Réinitialise toutes les séquences à 1
4. Insère les données de base des cryptomonnaies
5. Réactive les contraintes de clés étrangères
6. Affiche un rapport de vérification montrant le nombre d'enregistrements dans chaque table

## Important

- Assurez-vous de sauvegarder vos données importantes avant d'exécuter ce script
- Ce script supprime TOUTES les données de la base de données
- Seules les cryptomonnaies de base sont réinsérées
- Les utilisateurs devront se réinscrire après l'exécution de ce script

## Sécurité

Ce script doit être utilisé uniquement dans un environnement de développement ou de test.
NE PAS utiliser en production sans une sauvegarde complète des données.
