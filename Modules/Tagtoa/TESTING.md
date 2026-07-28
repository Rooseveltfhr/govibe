# TAGTOA — Plan de test / Plan tès

Base publique : **https://tagtoa.com/tapbiz/public**
Pou chak paj piblik, teste 4 lang : ajoute `?lang=ht`, `?lang=en`, `?lang=es`, `?lang=fr`.

---

## A. Tès otomatik (logique pure)
```bash
vendor/bin/phpunit --testdox --testsuite Unit
```
Kouvri : Luhn (loyalty), komisyon (billing), fòmataj lajan (Money).

## B. Smoke test piblik (otomatik nan deploy)
Mete nan anviwònman deploy la : `TAGTOA_SMOKE_BASE=https://tagtoa.com/tapbiz/public`
→ `remote-deploy.sh` ap verifye chak paj piblik retounen HTTP 200 (non bloctan).
Manyèlman :
```bash
for p in /menu/demo-menu /pay/demo /links/demo-links /event/demo-concert; do
  echo "$p -> $(curl -s -o /dev/null -w '%{http_code}' -L "https://tagtoa.com/tapbiz/public$p")"
done
```

## C. Checklist piblik (manyèl)
### MENU — `/menu/demo-menu`
- [ ] Kouvèti, logo, non, tip etablisman, slogan parèt
- [ ] Kategori yo (Entrées/Plats/Boissons) + navigasyon chip k ap defile
- [ ] Fich pwodwi: emoji, non, deskripsyon, badge, **pri** (fòma lajan)
- [ ] Panye: ajoute, +/−, total korèk
- [ ] « Commander sur WhatsApp » ouvri wa.me ak mesaj konplè
- [ ] « Payer » mennen sou `/pay/demo`
- [ ] `?lang=ht|en|es|fr`: tèks + (si meni an mete USD) pri an dola
- [ ] Sèlktè lang anlè adwat fonksyone + kenbe chwa a (cookie)

### PAY — `/pay/demo`
- [ ] Metòd yo parèt (MonCash, NatCash, Zelle, PayPal, USDT, BTC…) ak ikòn
- [ ] Kopye nimewo kont fonksyone
- [ ] Telechaje/voye prèv (capture) fonksyone
- [ ] Sèlktè lang

### LINKS — `/links/demo-links`
- [ ] Avatar, bio, lyen yo, rezo sosyal, bouton don
- [ ] Sèlktè lang (anlè agoch, pa antre an konfli ak « share »)

### EVENT — `/event/demo-concert`
- [ ] Enfo evènman, tip tikè, achte/lòd
- [ ] Sèlktè lang

### LOYALTY — `/loyalty/card/uvcudqvm9xsie6knrkhbdcok`
- [ ] Kat la parèt: balans, pwen, QR

## D. Checklist dashboard (konekte — `/tapbiz/public/login`)
Hub: `/tagtoa/home`
- [ ] Sèlktè lang nan topbar; tout meni tradui
- [ ] **MENU** `/tagtoa/menu`: kreye/modifye, kategori+pwodwi anbrike, upload logo/kouvèti,
      chwazi lajan (`<select>`), thème, koulè; sove; wè paj piblik
- [ ] **PAY** `/tagtoa/pay`: kreye paj, ajoute metòd, apwouve/rejte prèv
- [ ] **LOYALTY** `/tagtoa/loyalty`: pwogram, emèt kat, recharge/debite
- [ ] **LINKS** `/tagtoa/links`: kreye paj, ajoute lyen
- [ ] **EVENT** `/tagtoa/event`: kreye evènman, tip tikè, scanner checkin
- [ ] **POS** `/tagtoa/pos`: ouvri kès, vann, rapò Z
- [ ] **BILLING** `/tagtoa/billing`: chwazi abonman vs komisyon

## E. Apre chak deploy
1. CI vèt (lint + unit) ✅
2. Deploy `DEPLOY_OK` ✅
3. Smoke piblik: tout 200 ✅
4. Tès tach (spot check) youn nan panèl ki chanje yo.
