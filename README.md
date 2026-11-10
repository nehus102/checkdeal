# Vérif Annonce — MVP en mode valet

Un acheteur colle le lien d'une annonce d'occasion et son e-mail. Un analyste la vérifie dans le back-office, et l'acheteur reçoit un **indice de confiance de 0 à 100**, avec les raisons et des conseils. Quelques jours plus tard, on lui demande ce qu'il a décidé : c'est ce retour qui donne sa valeur à la base.

Le site est **agnostique au produit**. Il ne juge que les dimensions communes à toutes les annonces : le vendeur (note, nombre d'avis, ancienneté du compte), le prix comparé à des annonces équivalentes, la description, les photos et les conditions proposées. Le cadrage est dans `../../trust-intelligence/DATA_STRATEGY.md`.

## Lancer en local

Sur cette machine, les pilotes SQLite et GD sont installés mais pas activés dans `php.ini`. On les charge à chaque commande :

```bash
P="php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd -d extension=fileinfo"

$P artisan migrate                 # crée les tables dans database/database.sqlite
cd public && $P -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Ouvrir http://127.0.0.1:8000. Le back-office est sur `/admin`, avec le mot de passe `ADMIN_PASSWORD` du `.env`. Sans mot de passe, il reste fermé.

`php artisan serve` ne convient pas ici : il relance PHP sans ces extensions. Pour s'en passer, décommentez `extension=pdo_sqlite`, `extension=sqlite3`, `extension=gd` et `extension=fileinfo` dans `C:\Users\JB\Documents\php\php.ini`.

En local, les e-mails sont écrits dans `storage/logs/laravel.log` (`MAIL_MAILER=log`).

## Tests

```bash
$P vendor/bin/phpunit
```

`artisan test` relance PHPUnit dans un sous-processus sans les extensions : il faut lancer PHPUnit directement.

## Comment c'est organisé

| Où | Quoi |
|---|---|
| `app/Verification/RegleV1.php` | Les règles de scoring, en calcul pur. Changer un seuil ou un poids = créer une `RegleV2` : chaque évaluation garde sa version. |
| `app/Verification/EvaluationService.php` | Enregistre observations, évaluation et signaux, puis envoie le résultat. |
| `app/Verification/AnalyseDescription.php` | Repère des mots-clés dans la description (WhatsApp, virement, urgent…). Ce sont des suggestions, que l'analyste confirme. |
| `app/Verification/AnnonceUrl.php` | Normalise les liens (une annonce = une fiche) et reconnaît la plateforme. |
| `app/Http/Controllers/` | Parcours public (`DemandeController`), retours (`RetourController`), back-office (`AdminController`). |
| `database/migrations/2026_11_10_000100_create_verification_tables.php` | Le modèle de la Data Strategy : `listings`, `submissions`, `evidence`, `observations`, `assessments`, `signals`, `outcomes`. |

**Principes tenus par le code :**
- Rien n'est écrasé : un nouveau relevé ajoute une observation, et une réévaluation ajoute une évaluation.
- Un signal peut être présent, absent ou non vérifié. « Non vérifié » ne compte ni pour ni contre.
- Aucune identité de vendeur n'est stockée.
- Un achat abandonné n'est jamais compté comme une fraude, et une fraude déclarée sans preuve reste une suspicion.
- Les liens des e-mails de retour ouvrent une page de confirmation, sans rien enregistrer : les antivirus de messagerie ouvrent les liens.

## Tâches planifiées

Le cron du serveur doit lancer `php artisan schedule:run` chaque minute. Il déclenche :
- `verif:relances` à 10 h : la question « avez-vous acheté ? » à J+3, puis à J+10, uniquement si le demandeur a donné son accord ;
- `verif:purger-preuves` à 3 h : supprime les captures et preuves de plus de 12 mois.

## Avant d'ouvrir au public

- Faire valider la page `/confidentialite` et la base légale par un juriste (Data Strategy § 7).
- Choisir le nom et le domaine, configurer un vrai service d'envoi d'e-mails.
- Passer à PostgreSQL ou MySQL en production (`DB_CONNECTION`).
