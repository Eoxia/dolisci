# Changelog

## Unreleased

- Modèle PDF de facture « Quittance » : le modèle Sponge du cœur titré « Quittance » au lieu de
  « Facture ». Il remplace la copie de Sponge 15.0.2 posée à la main dans le cœur des instances,
  sous le même nom `quittance` (#1).
- Quittance : période louée (« Période : Du … au … ») en tête du document, tirée des dates des
  lignes. Demande de désactiver puis réactiver le module, pour le hook `pdfgeneration` (#6).
