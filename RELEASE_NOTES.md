# [DoliSecu] [23.1.0] - Connexion par URL pour comptes autorisés

Description : Cette version permet d'autoriser quelques comptes nommés à se connecter directement par un lien contenant les identifiants, même lorsque le captcha est activé, sans affaiblir la sécurité des autres utilisateurs.

**Cette version demande Saturne 23.6.0 ou supérieur.**

## Nouvelles fonctionnalités et innovations

### Connexion

* Nouveau réglage « Connexion par identifiants dans l'URL » : un sélecteur d'utilisateurs désigne les comptes autorisés à se connecter par un lien du type index.php?username=...&password=..., même quand le captcha est activé. Idéal pour les instances de démonstration.
* Pour tous les autres comptes, le captcha et le verrouillage anti-brute-force restent exigés ; ce verrouillage continue d'ailleurs de protéger aussi les comptes de la liste.

## Testez les nouveautés maintenant

**Allez tester la connexion par URL sur la démo : https://demodoli.digirisk.com**

## Comparaison des versions [23.0.0](https://github.com/Evarisk/DoliSecu/compare/23.0.0...23.1.0) et 23.1.0

* [#38] [Security] feat: liste blanche d'utilisateurs autorisés à se connecter par URL malgré le captcha [`ec659d01`](https://github.com/Evarisk/DoliSecu/commit/ec659d01)
