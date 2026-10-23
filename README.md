# DoliSci sur [DOLIBARR ERP CRM](https://dolibarr.org)

Outils pour les SCI (sociétés civiles immobilières) : modèle PDF de facture « Quittance », pour
les quittances de loyer.

## Informations

- Numéro du module : 436371
- Éditeur : [Evarisk](https://evarisk.com)
- Licence : GPLv3
- Compatibilité : Dolibarr 23.0 et suivants, PHP 7.4 et suivants
- Dépendances : module Factures

## Fonctionnalités

### Modèle de facture « Quittance »

Le modèle `quittance` est le modèle **Sponge** du cœur, dont seul le titre change : « Quittance »
au lieu de « Facture » (« Rent receipt » en anglais). Les avoirs, factures de remplacement,
d'acompte et proforma gardent leur titre habituel.

Il est activé pour l'entité courante en même temps que le module, puis se choisit comme les
autres modèles dans **Configuration → Modules → Factures**, ou sur chaque facture.

Comme il hérite de Sponge, il profite de toutes les corrections du cœur sans recopie.

## Installation sur une instance qui avait l'ancien modèle

Jusqu'en 2026, ce modèle était une copie de Sponge (Dolibarr 15.0.2) posée à la main dans
`core/modules/facture/doc/pdf_quittance.modules.php`. Avant d'activer le module, **supprimer ce
fichier du cœur** : sinon les deux fichiers déclarent la même classe `pdf_quittance` et la page
de configuration des factures tombe en erreur fatale.

Le nom du modèle (`quittance`) ne change pas : les factures existantes se régénèrent avec le
modèle du module.
