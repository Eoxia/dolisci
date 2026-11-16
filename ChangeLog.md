# Changelog

## Unreleased

- Modèle PDF de facture « Quittance » : le modèle Sponge du cœur titré « Quittance » au lieu de
  « Facture ». Il remplace la copie de Sponge 15.0.2 posée à la main dans le cœur des instances,
  sous le même nom `quittance` (#1).
- Quittance : période louée (« Période : Du … au … ») en tête du document, tirée des dates des
  lignes. Demande de désactiver puis réactiver le module, pour le hook `pdfgeneration` (#6).
- Modèle PDF de facture « Quittance de loyer » (`quittanceloyer`) au format du modèle de l'ANIL,
  rempli avec la facture ; avis d'échéance tant qu'elle n'est pas réglée, reçu si elle l'est en
  partie (#12).
- Quittance de loyer : le PDF de la facture est toujours l'avis d'échéance. La quittance (ou le
  reçu d'un paiement partiel) est un document à part, `Quittance_FA….pdf` (`Recu_FA….pdf`),
  écrit dès que la facture est réglée. Il est joint par défaut à l'envoi par email. Signature au
  nom de la société bailleresse ; locataire placé sous le bloc du bailleur, sans son nom en double ;
  « Échéance acquittée le … » sur l'avis d'une facture réglée (#18).
- Adresse du bien sur les deux modèles. Elle est lue dans l'attribut produit `adresse_bien` des
  lignes, et chaque adresse distincte est affichée une fois (loyer et parking, par exemple). Elle
  n'est jamais reprise du tiers ni du projet. Sans attribut rempli, le document affiche « Adresse
  du bien non renseignée ».
- Avis d'échéance de la quittance de loyer : RIB du compte de la facture, à défaut celui de
  `FACTURE_RIB_NUMBER`. Lien de paiement en ligne cliquable quand Stripe est actif et qu'il reste
  à payer, même en virement. Les quittances et les reçus n'ont ni RIB ni lien.
- Le modèle `quittance` s'appelle « Avis d'échéance » dans les listes. Son titre devient « Avis
  d'échéance acquitté » une fois la facture soldée. Il affiche le RIB quel que soit le mode de
  règlement, et le même lien de paiement. En prélèvement, il n'affiche ni RIB ni lien. Le
  libellé est posé à l'activation du module : il faut le désactiver puis le réactiver (#20).
