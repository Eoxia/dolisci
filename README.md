# DoliSCI sur [DOLIBARR ERP CRM](https://dolibarr.org)

Module pour la gestion de SCI simplement : projets immobiliers (immeubles et lots), produits biens achetés avec lot fiscal, services de location distincts (loyer principal `706100` et provision sur charges `708800`), isolation par tags/catégories dédiés et modèle PDF de facture « Quittance ».

## Informations

- Numéro du module : `436371`
- Famille : **Eoxia**
- Éditeur : [Eoxia](https://www.eoxia.com)
- Licence : GPLv3
- Compatibilité : Dolibarr 23.0 et suivants, PHP 7.4 et suivants
- Dépendances : modules Factures, Projets, Produits, Services, Tags/Catégories

## Fonctionnalités

### 1. Espace dédié DoliSCI (Menu haut & Tableau de bord)
- Entrée de menu supérieur **DoliSCI** avec logo couleur et tableau de bord synthétique (`dolisciindex.php`) affichant les indicateurs clés (nombre de projets immobiliers, nombre de lots fiscaux, nombre de services locatifs, total mensuel des loyers HT `706100` et des provisions sur charges HT `708800`).
- Menu gauche structuré autour des 3 piliers métier : **Projets de type biens**, **Produits type bien** et **Services type loyer / charges**.

### 2. Isolation native par Tags / Catégories DoliSCI
À l'activation (et via l'onglet d'administration **Initialisation**), DoliSCI crée et maintient 4 tags dédiés :
- **Bien immobilier** (`DOLISCI_TAG_PROJECT_PROPERTY`, catégorie Projet)
- **Bien / Lot immobilier** (`DOLISCI_TAG_PRODUCT_PROPERTY`, catégorie Produit)
- **Loyer (706100)** (`DOLISCI_TAG_SERVICE_RENT`, catégorie Service)
- **Charges locatives (708800)** (`DOLISCI_TAG_SERVICE_CHARGES`, catégorie Service)

Ces tags sont pré-sélectionnés à la création depuis l'espace DoliSCI, affectés automatiquement par trigger et utilisés comme filtres natifs sur les listes de projets, produits et services.

### 3. Projets immobiliers simplifiés (Immeubles & Lots)
- Activation native des sous-projets Dolibarr (`PROJECT_ENABLE_SUB_PROJECT = 1`) pour gérer la hiérarchie **Immeuble parent → Lots enfants**.
- Formulaire de création/édition de projet épuré dans l'espace DoliSCI : seuls **Réf.**, **Libellé**, **Parent**, **Usage** (décoché par défaut), **État** (toujours **Validé**), **Description**, **Tags/catégories** et **Type de projet SCI** sont affichés.
- Masquage automatique des onglets *Tâches* et *Temps consommé* sur les projets de type « Bien immobilier ».
- Rôles de contacts dédiés (`c_type_contact`, positions `43637110` à `43637160`) : **Adresse du bien immobilier**, **Locataire**, **Colocataire**, **Garant / Caution**, **Syndic de copropriété**, **Notaire**.
- Tableaux récapitulatifs sur la fiche du projet immobilier listant les biens/lots fiscaux rattachés et les services de location associés.

### 4. Produits « Biens achetés & Lots fiscaux » et Services « Loyers & Charges »
- **Produits (type `0`)** : rattachement au projet immobilier, nature du bien, régime juridique (copropriété / mono-propriété), n° de lot fiscal / réf. cadastrale, tantièmes, bâtiment/étage/porte, nombre de pièces, DPE / GES, date et prix d'acquisition.
- **Services (type `1`)** : séparation stricte entre le **Loyer nu / principal** (compte comptable `706100`) et la **Provision sur charges locatives** (compte comptable `708800`), rattachés au produit bien loué et au projet immobilier.
- **Génération en 1 clic** : bouton **« Générer Services Loyer + Charges »** sur la fiche d'un Produit bien immobilier pour créer et taguer automatiquement `SERV_LOYER_<REF>` (`706100`) et `SERV_PROV_CHARGES_<REF>` (`708800`).
- **Rattachement automatique des factures** : l'ajout d'une ligne de loyer ou de charges sur une facture client, une facture modèle récurrente ou une facture fournisseur affecte automatiquement le projet immobilier correspondant à la pièce.

### 5. Modèle de facture « Quittance »

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
