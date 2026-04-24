# LemonOups — bouton "Annuler cette facture" en un clic pour Dolibarr

Module Dolibarr qui ajoute un bouton **"Annuler cette facture"** sur la fiche d'une facture client validée **strictement sans aucun paiement**.

Ce bouton remplace le workflow natif Dolibarr en 5 clics sur 2 pages (Créer un avoir → cocher "mêmes lignes" → valider → convertir en crédit → utiliser le crédit) par **un seul clic**.

## Cas d'usage

Une facture validée qui ne peut plus être supprimée (car d'autres factures ont été émises après et la suppression casserait la séquence de numérotation), mais qui est au final invalide (erreur, client qui se désiste, devis annulé après transformation, etc.).

## Fonctionnement

En un clic, le module enchaîne en transaction atomique :

1. Création d'un avoir avec les **mêmes lignes** que la facture d'origine (montants inversés)
2. Validation de l'avoir (numérotation officielle)
3. Conversion de l'avoir en remise disponible (une par taux de TVA)
4. Marquage de l'avoir comme "payé" (= son crédit est consommé)
5. Imputation du crédit sur la facture d'origine
6. Marquage de la facture d'origine comme **"Payée"**

En cas d'erreur à n'importe quelle étape, la transaction est annulée (rollback). Aucune trace résiduelle.

## Conditions d'activation du bouton

Le bouton est toujours **visible** sur la fiche facture, mais **actif** uniquement si :

- La facture est de type **standard** (pas un avoir, acompte ou situation)
- La facture est **validée** (pas brouillon)
- La facture n'est **pas payée**
- **Aucun paiement** n'a été enregistré (même partiel)
- **Aucun acompte** n'a été imputé
- **Aucun avoir** n'a déjà été imputé

Sinon, le bouton est grisé et une infobulle explique précisément pourquoi.

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

## Désinstallation

Désactiver le module depuis l'interface. Supprimer le dossier `/opt/dolibarr/custom/lemonoups/` si souhaité. Aucune table SQL n'est créée par le module.

## Licence

GPL v3 — voir fichier LICENSE.

## Auteur

Lemon — [hellolemon.fr](https://hellolemon.fr)
