/* =====================================================
   TCHEKELA — Configuration des paiements
   -----------------------------------------------------
   Remplacez les valeurs de démo par vos vrais comptes.
   Pour l'automatisation (Binance Pay, CoinPayments, PayPal),
   ces clés doivent idéalement être utilisées côté SERVEUR
   (ne jamais exposer une clé secrète dans le navigateur).
   ===================================================== */
window.TCHEKELA_PAYMENTS = {
  // Lien PayPal.me (optionnel) — sinon généré automatiquement
  paypalLink: 'https://www.paypal.com/paypalme/tchekela',

  accounts: {
    moncash:  { label: 'MonCash (Digicel)', number: '+509 3398 8754', name: 'TCHEKELA / GOVIBE' },
    natcash:  { label: 'NatCash (Natcom)',  number: '+509 4012 0099', name: 'TCHEKELA / GOVIBE' },
    paypal:   { label: 'PayPal', email: 'don@tchekela.com' },
    zelle:    { label: 'Zelle', email: 'don@tchekela.com', phone: '+1 305 000 0000', name: 'TCHEKELA Inc.' },
    bank: {
      unibank:  { bank: 'Unibank',  account: '500-1234567-01', name: 'TCHEKELA / GOVIBE SA' },
      sogebank: { bank: 'Sogebank', account: '021-9876543-22', name: 'TCHEKELA / GOVIBE SA' }
    },
    crypto: {
      USDT: { label: 'USDT (TRC-20)',  address: 'TJ9xTcheKeLaUSDTdemoAddress0001xyz' },
      BTC:  { label: 'Bitcoin (BTC)',  address: 'bc1qtchekelademobtcaddress00000000xyz' },
      ETH:  { label: 'Ethereum (ETH)', address: '0xTCHEKELAdemoETHaddress00000000000000abcd' }
    }
  },

  /* ---------------------------------------------------
     INTÉGRATION API (à implémenter côté serveur)
     ---------------------------------------------------
     Exemples d'endpoints attendus par le front si vous
     voulez générer dynamiquement une facture de paiement :

     CoinPayments  : POST https://www.coinpayments.net/api.php
                     (cmd=create_transaction, key/secret côté serveur)
     Binance Pay   : POST /binance/order  (votre backend signe la requête)
     PayPal        : Orders API v2 (client-id public, secret serveur)

     Branchez ici une fonction qui retourne une URL/QR de paiement :
  --------------------------------------------------- */
  // createCryptoInvoice: async ({ amount, currency, asset }) => { ... return { address, qr, invoiceId }; }
};
