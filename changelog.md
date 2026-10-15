# CHANGELOG DOLISECU FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 23.0.0

Compatibilité : Dolibarr 23.0 à 24.0.

- Connexion : quand le captcha est activé, la connexion doit passer par le formulaire standard (#29).
- Connexion : verrouillage temporaire d'un compte (5 échecs) ou d'une adresse IP (20 échecs) pendant 15 minutes, réglable dans la configuration du module (#31).
- Module : bornes de version Dolibarr 23 minimum, 24 maximum (#19).
- CI : PHPStan, lint PHP et parité des langues (#21 à #27).

Après la mise à jour, réactiver le module : il crée sa table, ses réglages et ses hooks de connexion.

## 21.0.0

Ajout d'une permission spécifique pour les serveurs Windows, bouton de passage en production ou en développement, permissions de conf.php corrigées.

## 1.0

Initial version
