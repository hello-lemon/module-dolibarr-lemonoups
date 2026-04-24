# LemonOups — bouton "Émettre un avoir et solder" en un clic pour Dolibarr

Module Dolibarr qui ajoute un bouton **"Émettre un avoir et solder"** sur la fiche d'une facture client validée **strictement sans aucun paiement**.

Ce bouton **exécute automatiquement** le workflow natif Dolibarr (Créer un avoir → cocher "mêmes lignes" → valider → convertir en crédit → utiliser le crédit) qui demande normalement 5 clics sur 2 pages, en **un seul clic**.

## Cas d'usage

Une facture validée qui ne peut plus être supprimée (car d'autres factures ont été émises après et la suppression casserait la séquence de numérotation), mais qui est au final invalide (erreur, client qui se désiste, devis annulé après transformation, etc.).

## Fonctionnement

**Un seul clic remplace 6 opérations manuelles** que vous feriez sinon dans Dolibarr, l'une après l'autre, en cliquant partout :

1. Création d'un avoir avec les **mêmes lignes** que la facture d'origine (montants inversés)
2. Validation de l'avoir (numérotation officielle attribuée)
3. Conversion de l'avoir en remise disponible (une entrée par taux de TVA)
4. Marquage de l'avoir comme "payé" (= son crédit a été consommé)
5. Imputation du crédit sur la facture d'origine (ligne de remise créée)
6. Passage de la facture d'origine en **"Payée"**

Si une étape échoue, toutes les précédentes sont **automatiquement défaites** : la base reste exactement dans l'état où elle était avant le clic. Aucun avoir orphelin, aucune remise fantôme.

## Conditions d'affichage du bouton

Le bouton n'apparaît sur la fiche facture **que si toutes ces conditions sont réunies** :

- La facture est de type **standard** (pas un avoir, acompte ou situation)
- La facture est **validée** (pas brouillon)
- La facture n'est **pas payée**
- **Aucun paiement** n'a été enregistré (même partiel)
- **Aucun acompte** n'a été imputé
- **Aucun avoir** n'a déjà été imputé
- La facture n'est pas supprimable directement (sinon il suffit de cliquer sur Supprimer)

Sinon, le bouton n'est simplement pas affiché : on reste sur le workflow natif Dolibarr pour ces cas particuliers.

## Sécurité et traçabilité

- Uniquement le crédit issu de l'avoir créé est utilisé : **aucun autre crédit client** existant n'est touché
- Token CSRF vérifié sur chaque appel
- Permission requise : `facture > creer` (droit standard Dolibarr)
- Transaction SQL : soit tout réussit, soit rien ne s'écrit
- Les événements sont loggés automatiquement dans l'agenda Dolibarr via les triggers natifs (`BILL_CREATE`, `BILL_VALIDATE`, `BILL_PAYED`)

## Installation

### Pré-requis

- Dolibarr **>= 22.0**
- PHP **>= 7.4**

### Installation manuelle

```bash
cd /opt/dolibarr/custom/
git clone https://github.com/hello-lemon/module-dolibarr-lemonoups.git lemonoups
chown -R www-data:www-data lemonoups/
```

Puis dans Dolibarr : **Accueil → Configuration → Modules → LemonOups → Activer**.

## Page de configuration

Le module n'a aucun paramètre à régler. La page **Configuration → Modules → LemonOups → icône roue crantée** affiche simplement :

- Un bandeau **"Nouvelle version disponible"** si une release GitHub plus récente est publiée (check automatique, mis en cache 24 h, dégradé silencieusement si l'API GitHub est inaccessible)
- Un rappel que le module fonctionne sans configuration
- Un bloc **"À propos de Lemon"** (vitrine éditeur)

## Désinstallation

Désactiver le module depuis l'interface. Supprimer le dossier `/opt/dolibarr/custom/lemonoups/` si souhaité. Aucune table SQL n'est créée par le module.

## Licence

GPL v3 — voir fichier LICENSE.

## Auteur

Lemon — [hellolemon.fr](https://hellolemon.fr)
