# PiForge webshop (RaspBuilders)

Startklare webshop voor voorgeconfigureerde Raspberry Pi-sets. Statische HTML/CSS/JS plus een kleine PHP-API voor Mollie, PayPal en het contactformulier. Geen database, geen build-stap, geen Composer.

## Wat zit erin

| Pad | Inhoud |
| --- | --- |
| `index.html` | De hele site (hash-routing: `#/shop`, `#/product/...`, `#/afrekenen`, enz.) |
| `assets/data/products.json` | Producten, prijzen, opties, OS-images en verzendkosten. **Hier pas je je assortiment aan.** |
| `assets/js/config.js` | Bedrijfsgegevens (e-mail, KvK, btw, adres) en PayPal Client ID |
| `assets/js/app.js` | Winkelwagen, productconfigurator, checkout en pagina's |
| `api/` | PHP-endpoints: `mollie-create.php`, `mollie-webhook.php`, `order-status.php`, `paypal-confirm.php`, `contact.php` |
| `api/orders/` | Bestellingen als JSON-bestanden (afgeschermd met `.htaccess`) |

Pagina's: home, shop met filters per doelgroep, productconfigurator, toepassingen, OS-images, winkelwagen, afrekenen, bedankt, verzending & retour (incl. modelformulier herroeping), algemene voorwaarden, privacybeleid en contact.

## Live zetten in 5 stappen

1. **Hosting**: elke Nederlandse webhost met PHP 8.0+ en cURL (bijv. Vimexx, Hostinger, TransIP, Antagonist). Upload de hele map naar `public_html/` of `httpdocs/`. Zorg dat SSL (https) aanstaat.
2. **Config**: kopieer `api/config.example.php` naar `api/config.php` en vul in:
   - `mollie_api_key`: je sleutel uit het [Mollie Dashboard](https://my.mollie.com) (eerst `test_...`, later `live_...`).
   - `site_url`: je domein, bijv. `https://piforge.nl` (zonder `/` aan het eind).
   - `from_email`: een adres op je eigen domein (betere bezorging van mails).
   - Optioneel `paypal_client_id` en `paypal_secret` voor server-side controle van PayPal-betalingen.
3. **PayPal-knop**: maak een app aan op [developer.paypal.com](https://developer.paypal.com) en zet de Client ID in `assets/js/config.js` bij `paypalClientId`.
4. **Bedrijfsgegevens**: vul KvK-nummer, btw-id en adres in `assets/js/config.js` in. Deze verschijnen automatisch in de footer en de voorwaarden.
5. **Schrijfrechten**: zorg dat de map `api/orders/` schrijfbaar is voor PHP (meestal standaard; anders `chmod 755`).

Test daarna een bestelling met de Mollie-testsleutel. Werkt alles, vervang de sleutel door je `live_`-sleutel.

## Hoe betalen werkt

- **Mollie** (iDEAL, Bancontact, creditcard): de browser stuurt de winkelwagen naar `api/mollie-create.php`. De server **berekent de prijs opnieuw** uit `products.json` (dus niet te manipuleren), maakt de betaling aan en stuurt de klant naar Mollie. Mollie meldt de status via `mollie-webhook.php`; bij `paid` gaan er bevestigingsmails naar klant en naar `raspbuilders@astermail.org`.
- **PayPal**: de PayPal-knop verschijnt bij het afrekenen. Na betaling controleert `paypal-confirm.php` (met je secret) of het bedrag klopt en slaat de bestelling op. Zonder secret krijg je een mail om de betaling handmatig te controleren.

Zonder PHP (bijv. op GitHub Pages of Netlify) werkt de hele site, maar tonen de betaalknoppen een duidelijke melding en valt het contactformulier terug op een `mailto:`-link.

## Assortiment aanpassen

Open `assets/data/products.json`. Per product stel je in: naam, prijs, doelgroepen (`gaming`, `smarthome`, `opslag`, `server`, `linux`, `media`, `vpn`, `onderwijs`), RAM- en opslagvarianten met meerprijs, toegestane OS-images en extra's. Afbeeldingen staan in `assets/img/` (veld `image` is de bestandsnaam zonder `.jpg`).

## Juridisch

De voorwaarden en het privacybeleid zijn een stevige basis voor een Nederlandse webshop (herroepingsrecht 14 dagen, wettelijke garantie, AVG), maar laat ze bij twijfel nalezen of vergelijk met de modelvoorwaarden van Thuiswinkel.org of ondernemersplein.nl. Vergeet niet je KvK- en btw-nummer in te vullen.

Raspberry Pi is een handelsmerk van Raspberry Pi Ltd. PiForge is een onafhankelijke reseller.
