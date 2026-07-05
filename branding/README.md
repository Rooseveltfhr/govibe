# rooseveltforestal.com — Site de personal branding

Site statique premium (Noir · Blanc · Or, titres **Anton**, corps **Inter**).
Polices **auto-hébergées** dans `assets/fonts/` (aucune dépendance Google Fonts).
Aucun build, aucune dépendance : HTML + CSS + JS vanilla. Prêt pour n'importe quel
hébergement (DirectAdmin, cPanel, Netlify, GitHub Pages…).

## Pages

| Fichier | Contenu |
|---|---|
| `index.html` | Accueil : hero, statistiques animées, aperçu About/Vision/Écosystème, impact, témoignages, CTA |
| `about.html` | Biographie exécutive, rôles, My Story (timeline documentaire), impact chiffré, distinctions |
| `ecosystem.html` | GOVIBE Innovation Hub, Academy, AI Solutions, TAGTOA/SaaS, services, incubateur, roadmap |
| `speaking.html` | Sujets de conférence, pourquoi réserver, galerie scène, **Calendly intégré** (`#book`) |
| `media.html` | Galerie photos, articles, presse/media kit, témoignages |
| `contact.html` | Canaux directs (WhatsApp, email, LinkedIn, Facebook), formulaire, Calendly |

SEO inclus sur chaque page : meta title/description/keywords, Open Graph, canonical,
JSON-LD (Person + Organization), `sitemap.xml`, `robots.txt`.

## Déploiement (DirectAdmin)

Téléverser **le contenu du dossier `branding/`** dans le docroot du domaine
`rooseveltforestal.com` (ex. `public_html/`). Rien d'autre à configurer.

## ⚙️ Configuration — 1 seul endroit

Tout est dans `assets/js/main.js`, bloc `RF` en haut du fichier :

```js
const RF = {
  calendly: "https://calendly.com/rooseveltforestal", // ← REMPLACER par votre vrai lien Calendly
  whatsapp: "https://wa.me/message/5FJZXWSPZRJHB1",
  facebook: "https://www.facebook.com/share/1ELxgXJDTx/",
  linkedin: "https://www.linkedin.com/in/roosevelt-forestal-530864145",
  email: "contact@rooseveltforestal.com", // ← REMPLACER si besoin
};
```

**À faire avant la mise en ligne :**
1. Remplacer `calendly` par votre lien Calendly réel (sinon le widget affichera une page vide).
2. Vérifier l'adresse `email` (utilisée par le formulaire de contact).
3. Ajuster les **statistiques** (`data-count` dans `index.html` et `about.html`) avec vos vrais chiffres.
4. Remplacer les **témoignages** d'exemple (`index.html` + `media.html`) par de vrais témoignages
   dès que vous les avez — les cartes actuelles sont des gabarits.

## 📸 Photos — noms de fichiers attendus

Le repo [Personal-branding-images](https://github.com/Rooseveltfhr/Personal-branding-images)
était **vide** au moment de la construction. Le site affiche donc des placeholders élégants
(monogramme or sur noir). Dès qu'une image existe sous le bon nom dans `assets/img/`,
elle s'affiche automatiquement — aucun code à changer.

Déposer les photos dans `assets/img/` avec ces noms (JPEG recommandé, ~1600px max) :

| Fichier | Emplacement | Format conseillé |
|---|---|---|
| `hero-portrait.jpg` | Accueil — portrait principal | Vertical 4:5, votre meilleure photo |
| `about-portrait.jpg` | Accueil — section About | Vertical 4:5 |
| `bio-portrait.jpg` | About — biographie | Vertical 4:5 |
| `impact.jpg` | Accueil — section Impact | Vertical/carré (formation, terrain) |
| `hub.jpg` | Ecosystem — Innovation Hub | Locaux / équipe |
| `academy.jpg` | Ecosystem — Academy | Formation / classe |
| `ai.jpg` | Ecosystem — AI Solutions | Tech / démo |
| `tagtoa.jpg` | Ecosystem — TAGTOA | Produit / carte NFC |
| `speaking-hero.jpg` | Speaking — portrait scène | Vertical 4:5, micro/scène |
| `speaking-stage.jpg` | Speaking — section « Why book » | Sur scène |
| `speaking-1.jpg` … `speaking-5.jpg` | Speaking — galerie | Carré ok |
| `gallery-1.jpg` … `gallery-7.jpg` | Media — galerie | Mixte (events, training, lifestyle) |
| `testimonial-1.jpg` … `testimonial-3.jpg` | Témoignages — avatars | Carré, visage |
| `og-cover.jpg` | Partage réseaux sociaux (Open Graph) | 1200×630 horizontal |

## Test local

```bash
cd branding && python3 -m http.server 8080
# → http://localhost:8080
```
